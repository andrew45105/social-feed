<?php

namespace App\Controller;

use App\Entity\InstagramAccount;
use App\Entity\User;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use JasonGrimes\Paginator;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Annotation\Route;
use GuzzleHttp\Promise;

/**
 * Class IndexController
 * @package App\Controller
 */
class IndexController extends BaseController
{
    /**
     * @Route("/instagram", name="instagram_feed")
     *
     * @param LoggerInterface           $logger
     * @param CacheItemPoolInterface    $cache
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function indexAction(LoggerInterface $logger, CacheItemPoolInterface $cache)
    {
        $pagePosts = [];

        /** @var User $user */
        $user = $this->getUser();

        try {
            $page           = $this->getPage();
            $postsPerPage   = $this->getParameter('app.instagram_posts_per_page');
            $cacheFeed      = $cache->getItem('instagram_feed_' . $user->getId());

            if (!$cacheFeed->isHit()) {
                $accounts = $user->getInstagramAccounts()->filter(function (InstagramAccount $a) {
                    return !$a->isNeedUpdate();
                });
                if (!count($accounts)) {
                    throw new \Exception('No instagram accounts find');
                }

                $cacheSeconds           = (int)$this->getParameter('app.instagram_cache_seconds');
                $baseRoute              = $this->getParameter('app.instagram_base_route');
                $daysToSearch           = $this->getParameter('app.instagram_days_to_search_content');
                $maxPostsInFeed         = $this->getParameter('app.instagram_max_posts_in_feed');
                $timestampToSearchFrom  = time() - $daysToSearch * 60 * 60 * 24;

                $client = new Client(['base_uri' => $baseRoute]);

                $promises = [];

                $accountsData = [];

                /** @var InstagramAccount $account */
                foreach ($accounts as $account) {
                    $accountsData[$account->getExternalId()] = $account->getUsername();
                }

                // Making async requests
                foreach ($accountsData as $externalId => $username) {
                    $promises[$externalId] = $client->getAsync("/$username/?__a=1");
                }

                $results = Promise\settle($promises)->wait();

                $posts = [];
                $allPosts = [];

                foreach ($results as $accountId => $result) {

                    if ($result['state'] === 'fulfilled') {
                        /** @var Response $response */
                        $response = $result['value'];
                        if ($response->getStatusCode() == 200) {
                            $data = json_decode($response->getBody()->getContents(), true);
                            $actualAccountId = $data['graphql']['user']['id'] ?? null;
                            $logger->info(json_encode($response->getHeaders()));

                            // If ids not similar, means another instagram account has this username
                            if ($accountId != $actualAccountId) {
                                // Set account as need update
                                $this->setNeedUpdate($accountId);
                                $posts[$accountId] = "instagram username changed";
                            } else {
                                $nodes = $data['graphql']['user']['edge_owner_to_timeline_media']['edges'] ?? [];
                                foreach ($nodes as $node) {
                                    $photo = $node['node'];
                                    $timestamp = $photo['taken_at_timestamp'];
                                    if ($timestamp < $timestampToSearchFrom) {
                                        continue;
                                    }
                                    $posts[$accountId][] = [
                                        'shortcode' => $photo['shortcode'],
                                        'timestamp' => $timestamp,
                                    ];
                                }
                            }

                        } else {
                            $posts[$accountId] = "http-code: {$response->getStatusCode()}";
                        }
                    } else if ($result['state'] === 'rejected') {
                        // Set account as need update
                        //$this->setNeedUpdate($accountId);
                        $posts[$accountId] = "rejected - {$result['reason']}";
                    } else {
                        $posts[$accountId] = "unknown exception";
                    }
                }

                // Modify data to needed structure
                foreach ($posts as $accountId => $data) {
                    if (is_array($data)) {
                        $allPosts = array_merge($allPosts, $data);
                    } else {
                        $logger->error("Get posts for instagram account $accountId error: $data");
                    }
                }
                // Sort data by published date
                usort($allPosts, function (array $photo1, array $photo2) {
                    return $photo2['timestamp'] <=> $photo1['timestamp'];
                });
                // Slice data to max posts in feed
                $allPosts = array_slice($allPosts, 0, $maxPostsInFeed);
                // Save in cache
                $cacheFeed->set($allPosts)->expiresAfter($cacheSeconds);
                $cache->save($cacheFeed);
            } else {
                $allPosts = $cacheFeed->get();
            }

            // Pagination
            $pagePosts = array_slice($allPosts, $postsPerPage * ($page - 1), $postsPerPage);
            $paginator = new Paginator(count($allPosts), $postsPerPage, $page, '?p=(:num)');

        } catch (\Throwable $e) {
            $logger->error("Get instagram posts for user {$user->getEmail()} error: {$e->getMessage()}");
        }

        return $this->render('instagram.html.twig', [
            'posts'     => $pagePosts,
            'paginator' => $paginator ?? null,
        ]);
    }
}