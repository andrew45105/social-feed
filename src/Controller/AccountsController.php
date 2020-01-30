<?php

namespace App\Controller;

use App\Entity\InstagramAccount;
use App\Entity\User;
use App\Entity\VkAccount;
use App\Utils\AccountUtils;
use App\Utils\FileUtils;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class AccountsController
 * @package App\Controller
 */
class AccountsController extends BaseController
{
    /**
     * @Route("/", name="index", methods={"GET"})
     *
     * @param CacheItemPoolInterface $cache
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function listAction(CacheItemPoolInterface $cache)
    {
        $cacheItem = $cache->getItem('vk_token_' . $this->getUser()->getId());
        // If no vk token in cache, got it
        if (!$cacheItem->isHit()) {
            return $this->redirectToRoute('vk_feed_code', [
                'from' => 'index',
            ]);
        }
        return $this->render('accounts.html.twig');
    }

    /**
     * @Route("/accounts", name="accounts_delete", methods={"DELETE"})
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function deleteAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        /** @var User $user */
        $user = $this->getUser();
        $id = $request->request->get('id');
        $type = $request->request->get('type');

        $class = AccountUtils::getAccountClassByType($type);
        $repo = $em->getRepository($class);
        if (!$account = $repo->find($id)) {
            throw new NotFoundHttpException("Account not found (id=$id, type=$type)");
        }
        if ($account instanceof InstagramAccount) {
            $user->removeInstagramAccount($account);
        }
        if ($account instanceof VkAccount) {
            $user->removeVkAccount($account);
        }
        $em->persist($user);
        $em->flush();

        return $this->json([]);
    }

    /**
     * @Route("/accounts", name="accounts_create", methods={"POST"})
     *
     * @param Request                   $request
     * @param CacheItemPoolInterface    $cache
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws InvalidArgumentException
     * @throws ConflictHttpException
     */
    public function createAction(Request $request, CacheItemPoolInterface $cache)
    {
        try {
            $em = $this->getDoctrine()->getManager();

            /** @var User $user */
            $user   = $this->getUser();
            $link   = $request->request->get('link');
            $vkLink = $instagramLink = false;
            $incorrectLinkMessage = "Link $link incorrect. Type correct instagram or vk profile link";

            if (preg_match('/https:\/\/(www.)?instagram.com\/\\w+/', $link)) {
                $instagramLink = true;
            } elseif (preg_match('/https:\/\/vk.com\/\\w+/', $link)) {
                $vkLink = true;
            } else {
                throw new InvalidArgumentException($incorrectLinkMessage);
            }
            if ($instagramLink) {
                // Check profile request
                $response   = @file_get_contents($link . '?__a=1');
                $response   = json_decode($response, true);
                $responseId = $response['graphql']['user']['id'] ?? null;
                if (!$responseId) {
                    throw new InvalidArgumentException($incorrectLinkMessage);
                }
                if (!$account = $em->getRepository(InstagramAccount::class)->findOneBy(['externalId' => $responseId])) {
                    $account = new InstagramAccount();
                    $account->setExternalId($responseId);
                    $account->setUsername($response['graphql']['user']['username']);
                    $em->persist($account);
                    $em->flush();
                }
                if ($user->hasInstagramAccount($account)) {
                    throw new ConflictHttpException('You are already have this account');
                }
                $user->addInstagramAccount($account);
                $em->persist($user);
                $em->flush();
            }
            if ($vkLink) {
                $cacheToken = $cache->getItem('vk_token_' . $this->getUser()->getId());
                // If no vk token
                if (!$cacheToken->isHit()) {
                    throw new InvalidArgumentException('No given vk token');
                }
                // Check profile request
                $urlTemplate    = $this->getParameter('app.vk_user_route');
                $apiVersion     = $this->getParameter('app.vk_api_version');
                $userId         = $this->getVkUserId($link);
                $token          = $cacheToken->get();
                $url            = sprintf($urlTemplate, $userId, $token, $apiVersion);
                // API request
                $response   = @file_get_contents($url);
                $response   = json_decode($response, true);
                $responseId = $response['response'][0]['id'] ?? null;
                if (!$responseId) {
                    throw new InvalidArgumentException($incorrectLinkMessage);
                }
                if (!$account = $em->getRepository(VkAccount::class)->findOneBy(['externalId' => $responseId])) {
                    $account = new VkAccount();
                    $account->setExternalId($responseId);
                    $account->setUsername($response['response'][0]['first_name'] . ' ' . $response['response'][0]['last_name']);
                    $em->persist($account);
                    $em->flush();
                }
                if ($user->hasVkAccount($account)) {
                    throw new ConflictHttpException('You are already have this account');
                }
                $user->addVkAccount($account);
                $em->persist($user);
                $em->flush();
            }
            // Record data to file
            $accountsFile = FileUtils::getArrayDataFromJsonFile(FileUtils::ACCOUNTS_FILE);
            if (!AccountUtils::existInFile($accountsFile, $account)) {
                $accountsFile[] = [$account->getExternalId(), $account->getUsername(), AccountUtils::getAccountTypeByObject($account)];
                FileUtils::putJsonData($accountsFile, FileUtils::ACCOUNTS_FILE);
            }

        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()]);
        }

        return $this->json([]);
    }
}