<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\InstagramAccountRepository;
use App\Repository\VkAccountRepository;
use App\Utils\AccountUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class UserAccountsController
 * @package App\Controller
 */
class UserAccountsController extends BaseController
{
    /**
     * @Route("/user/accounts", name="user_accounts_add", methods={"POST"})
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function addAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        /** @var User $user */
        $user = $this->getUser();
        $type = $request->request->get('type');

        AccountUtils::checkType($type);

        $class          = AccountUtils::getAccountClassByType($type);
        /** @var InstagramAccountRepository|VkAccountRepository $repo */
        $repo           = $em->getRepository($class);
        $accounts       = $repo->findAll();
        $accountsIds    = array_map(function ($account) {return $account->getId();}, $accounts);

        $repo->addAllToUser($user->getId(), $accountsIds);

        return $this->json([]);
    }

    /**
     * @Route("/user/accounts", name="user_accounts_delete", methods={"DELETE"})
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
        $type = $request->request->get('type');

        AccountUtils::checkType($type);

        $class  = AccountUtils::getAccountClassByType($type);
        /** @var InstagramAccountRepository|VkAccountRepository $repo */
        $repo   = $em->getRepository($class);
        $repo->deleteAllFromUser($user->getId());

        return $this->json([]);
    }
}