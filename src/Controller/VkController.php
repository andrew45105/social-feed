<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\VkAccount;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class VkController
 * @package App\Controller
 */
class VkController extends BaseController
{
    /**
     * @Route("/vk/code", name="vk_feed_code")
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function codeAction(Request $request)
    {
        $from = $request->query->get('from');

        $urlTemplate    = $this->getParameter('app.vk_auth_code_route');
        $clientId       = $this->getParameter('app.vk_client_id');
        $apiVersion     = $this->getParameter('app.vk_api_version');
        $redirectUri    = $this->getParameter('app.vk_redirect_uri');
        $url            = sprintf($urlTemplate, $clientId, $apiVersion, $from, $redirectUri);
        // Get vk authorization code
        return $this->redirect($url);
    }

    /**
     * @Route("/vk/token", name="vk_feed_token")
     *
     * @param Request                   $request
     * @param CacheItemPoolInterface    $cache
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function tokenAction(Request $request, CacheItemPoolInterface $cache)
    {
        $cacheToken = $cache->getItem('vk_token_' . $this->getUser()->getId());

        $code = $request->query->get('code');
        $from = $request->query->get('state');
        if (!$code && !$from) {
            return new Response('No given authorization code or referer');
        }
        // Get url for vk API token
        $urlTemplate    = $this->getParameter('app.vk_access_token_route');
        $clientId       = $this->getParameter('app.vk_client_id');
        $clientSecret   = $this->getParameter('app.vk_client_secret');
        $redirectUri    = $this->getParameter('app.vk_redirect_uri');
        $url            = sprintf($urlTemplate, $clientId, $clientSecret, $redirectUri, $code);
        // Get vk API token
        $response       = file_get_contents($url);
        $response       = json_decode($response, true);
        $token          = $response['access_token'] ?? 'token';
        $expiredSec     = (int)$this->getParameter('app.vk_token_cache_seconds');
        // Save in cache
        $cacheToken->set($token)->expiresAfter($expiredSec);
        $cache->save($cacheToken);

        return $this->redirectToRoute($from);
    }

    /**
     * @Route("/vk", name="vk_feed")
     *
     * @param CacheItemPoolInterface $cache
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function indexAction(CacheItemPoolInterface $cache)
    {
        $cacheToken = $cache->getItem('vk_token_' . $this->getUser()->getId());
        // If no vk token in cache, got it
        if (!$cacheToken->isHit()) {
            return $this->redirectToRoute('vk_feed_code', [
                'from' => 'vk_feed',
            ]);
        }

        return $this->render('vk.html.twig');
    }

    /**
     * @Route("/vk/ajax", name="vk_feed_ajax")
     *
     * @param CacheItemPoolInterface $cache
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function ajaxAction(CacheItemPoolInterface $cache)
    {
        $posts = [];
        /** @var User $user */
        $user = $this->getUser();
        /** @var EntityManagerInterface $em */
        $em = $this->getDoctrine()->getManager();

        try {
            $postsPerPage   = $this->getParameter('app.vk_posts_per_page');
            $page           = $this->getPage();
            $cacheFeed      = $cache->getItem('vk_feed_' . $user->getId());

            if (!$cacheFeed->isHit()) {
                $accounts = $user->getVkAccounts()->filter(function (VkAccount $a) {
                    return true;//!$a->isNeedUpdate();
                });
                if (!count($accounts)) {
                    throw new \Exception('No vk accounts find for user');
                }

                $cacheSeconds           = (int)$this->getParameter('app.vk_cache_seconds');
                $daysToSearch           = $this->getParameter('app.vk_days_to_search_content');
                $maxPostsInFeed         = $this->getParameter('app.vk_max_posts_in_feed');
                $timestampToSearchFrom  = time() - $daysToSearch * 60 * 60 * 24;

                $timeOutMsec    = intval(pow(10, 6) * floatval($this->getParameter('app.vk_api_seconds_timeout')));
                $urlTemplate    = $this->getParameter('app.vk_photos_route');
                $apiVersion     = $this->getParameter('app.vk_api_version');
                $cacheToken     = $cache->getItem('vk_token_' . $user->getId());
                if (!$cacheToken->isHit()) {
                    throw new \Exception('No token given');
                } else {
                    $token = $cacheToken->get();
                }

                /** @var VkAccount $account */
                foreach ($accounts as $account) {
                    // Latency between requests
                    usleep($timeOutMsec);

                    $ownerId = $account->getExternalId();
                    $url = sprintf($urlTemplate, $token, $apiVersion, $ownerId);
                    // Request to vk API
                    $response = file_get_contents($url);
                    $response = json_decode($response, true);

                    if (!isset($response['response']['items'])) {
                        // Set profile as need update
                        //$account->setNeedUpdate(true);
                        //$em->persist($account);
                        //$em->flush();
                        continue;
                    }
                    // Get vk user photos
                    foreach ($response['response']['items'] as $photo) {
                        if (!isset($photo['sizes']) || !is_array($photo['sizes'])) {
                            continue;
                        }
                        if (!$link = $this->getBiggestPhoto($photo['sizes'])) {
                            continue;
                        }
                        $timestamp = intval($photo['date']);
                        if ($timestamp < $timestampToSearchFrom) {
                            continue;
                        }
                        $posts[] = [
                            'link'      => $link,
                            'text'      => $photo['text'] ?? null,
                            'timestamp' => $timestamp,
                            'name'      => $account->getUsername(),
                            'profile'   => "https://vk.com/id{$account->getExternalId()}"
                        ];
                    }
                }

                // Sort data by published date
                usort($posts, function (array $photo1, array $photo2) {
                    return $photo2['timestamp'] <=> $photo1['timestamp'];
                });

                // Add full date field to each photo
                array_walk($posts, function (&$post) {
                    $post['date'] = (new \DateTime())->setTimestamp($post['timestamp'])->format('d M H:i:s');
                });

                // Slice data to max posts in feed
                $posts = array_slice($posts, 0, $maxPostsInFeed);
                // Save in cache
                $cacheFeed->set($posts)->expiresAfter($cacheSeconds);
                $cache->save($cacheFeed);
            } else {
                $posts = $cacheFeed->get();
            }

            // Pagination
            $posts = array_slice($posts, $postsPerPage * ($page - 1), $postsPerPage);

        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()]);
        }

        return $this->json($posts);
    }
}