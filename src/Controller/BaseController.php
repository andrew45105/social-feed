<?php

namespace App\Controller;

use App\Entity\InstagramAccount;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Class BaseController
 * @package App\Controller
 */
class BaseController extends AbstractController
{
    /**
     * Get link to biggest photo from array of VK user photos
     *
     * @param array $sizes
     *
     * @return string|null
     */
    public function getBiggestPhoto($sizes)
    {
        $letters = ['w', 'z', 'y', 'x', 'r', 'q', 'p', 'o', 'm', 's'];
        foreach ($letters as $letter) {
            foreach ($sizes as $size) {
                if ($size['type'] === $letter) {
                    return $size['url'];
                }
            }
        }
        return null;
    }

    /**
     * Get current page number for pagination
     *
     * @param string $pageParamName
     *
     * @return int
     */
    public function getPage(string $pageParamName = 'p')
    {
        $page = (int)$this->get('request_stack')->getCurrentRequest()->query->get($pageParamName);
        return $page ? $page : 1;
    }

    /**
     * Set instagram account as need updated (when user changed his username)
     *
     * @param string $account
     */
    public function setNeedUpdate($accountId)
    {
        /** @var EntityManagerInterface $em */
        $em = $this->getDoctrine()->getManager();
        /** @var InstagramAccount $account */
        $account = $em->getRepository(InstagramAccount::class)->findOneBy([
            'externalId' => $accountId,
            'needUpdate' => false,
        ]);
        if ($account) {
            $account->setNeedUpdate(true);
            $em->persist($account);
            $em->flush();
        }
    }

    /**
     * Get VK user id (numeric or short url) from his page link
     *
     * @param string $link
     *
     * @return string
     */
    public function getVkUserId(string $link)
    {
        preg_match('/https:\/\/vk.com\/(.+)/', $link, $matches1);
        $id = $matches1[1];
        preg_match('/id(\\d+)/', $id, $matches2);
        $numericId = $matches2[1] ?? null;
        return $numericId ?? $id;
    }
}