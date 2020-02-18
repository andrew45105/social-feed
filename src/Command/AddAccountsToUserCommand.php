<?php

namespace App\Command;

use App\Entity\InstagramAccount;
use App\Entity\User;
use App\Entity\VkAccount;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Class AddAccountsToUserCommand
 * @package App\Command
 */
class AddAccountsToUserCommand extends Command
{
    const ADMIN_USERNAME = 'admin';

    protected static $defaultName = 'app:add-accounts-to-user';

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var ParameterBagInterface
     */
    private $params;

    /**
     * @var array
     */
    private $repos;

    /**
     * AddAccountsToUserCommand constructor.
     * @param EntityManagerInterface $em
     * @param ParameterBagInterface $params
     */
    public function __construct(EntityManagerInterface $em, ParameterBagInterface $params)
    {
        parent::__construct();
        $this->em               = $em;
        $this->repos            = [
            InstagramAccount::class    => $em->getRepository(InstagramAccount::class),
            VkAccount::class           => $em->getRepository(VkAccount::class),
        ];
        $this->params           = $params;
    }

    /**
     * @{@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->addArgument('username', InputArgument::OPTIONAL, 'User name, default - main admin user')
            ->setDescription('Adding all instagram and vk accounts to needed user');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        if (!$username = $input->getArgument('username')) {
            $username = self::ADMIN_USERNAME;
        }

        try {
            /** @var User|null $user */
            if (!$user = $this->em->getRepository(User::class)->findOneBy(['username' => $username])) {
                throw new \RuntimeException("User {$username} not found");
            }

            $accountsDB = [
                InstagramAccount::class => $this->getObjectsIds($this->repos[InstagramAccount::class]->findAll()),
                VkAccount::class        => $this->getObjectsIds($this->repos[VkAccount::class]->findAll()),
            ];
            $accountsUser = [
                InstagramAccount::class => $this->getObjectsIds($user->getInstagramAccounts()->toArray()),
                VkAccount::class        => $this->getObjectsIds($user->getVkAccounts()->toArray()),
            ];
            $accountsNeed = [
                InstagramAccount::class => array_diff($accountsDB[InstagramAccount::class], $accountsUser[InstagramAccount::class]),
                VkAccount::class        => array_diff($accountsDB[VkAccount::class], $accountsUser[VkAccount::class]),
            ];

            foreach ($accountsNeed as $type => $ids) {
                if (!$ids) {
                    $output->writeln("New accounts $type for user not found");
                    continue;
                }
                $output->writeln("Found new accounts $type for user: " . implode(',', $ids));
                $output->writeln('Adding accounts...');
                $accounts = $this->repos[$type]->findBy(['id' => $ids]);
                foreach ($accounts as $account) {
                    if ($account instanceof InstagramAccount) {
                        $user->addInstagramAccount($account);
                    }
                    if ($account instanceof VkAccount) {
                        $user->addVkAccount($account);
                    }
                    $this->em->persist($user);
                }
                $this->em->flush();
            }

            $output->writeln('Done!');

        } catch (\Throwable $e) {
            $io->error(
                'An unexpected error occurred'.PHP_EOL.PHP_EOL.
                $e->getMessage()
            );
        }
    }

    /**
     * Get array of object ids
     *
     * @param array $objects
     *
     * @return array
     */
    private function getObjectsIds(array $objects)
    {
        return array_map(function ($object) {return $object->getId();}, $objects);
    }
}