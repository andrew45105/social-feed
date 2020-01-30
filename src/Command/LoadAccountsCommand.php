<?php

namespace App\Command;

use App\Entity\InstagramAccount;
use App\Entity\User;
use App\Entity\VkAccount;
use App\Utils\AccountUtils;
use App\Utils\FileUtils;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Class LoadAccountsCommand
 * @package App\Command
 */
class LoadAccountsCommand extends Command
{
    const MODE_FROM_FILE            = 'from_file';
    const MODE_FROM_DB              = 'from_db';
    const ADMIN_USERNAME            = 'admin';

    protected static $defaultName   = 'app:load-accounts';

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
     * LoadAccountsCommand constructor.
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
            ->addArgument('mode', InputArgument::REQUIRED, 'Mode: from file to database or from database to file (from_file / from_db)')
            ->setDescription('Load instagram/vk accounts data in database from file and conversely');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $mode = $input->getArgument('mode');
            if (!in_array($mode, $this->getModes())) {
                throw new \RuntimeException('Incorrect working mode: ' .  $mode);
            }

            /** @var User $user */
            $user = $this->em->getRepository(User::class)->findOneBy(['username' => self::ADMIN_USERNAME]);
            $accountsDB = array_merge(
                $this->repos[InstagramAccount::class]->findAll(),
                $this->repos[VkAccount::class]->findAll()
            );
            $accountsFile = FileUtils::getArrayDataFromJsonFile(FileUtils::ACCOUNTS_FILE);

            if ($mode === self::MODE_FROM_FILE) {
                $output->writeln('Recording accounts data from file to database');

                $accountsDB = $this->modifyAccountsData($accountsDB);

                $newAccounts = [];
                /** @var array $account */
                foreach ($accountsFile as $account) {
                    if (!isset($accountsDB[$account[2]][$account[0]])) {
                        $newAccounts[$account[2]] = $account;
                    }
                }
                if (!$newAccounts) {
                    $output->writeln('New accounts not found!');
                    return;
                }
                $io->table(['id', 'username', 'type'], $newAccounts);

                // Add new accounts to database
                /** @var array $account */
                foreach ($newAccounts as $account) {
                    $object = AccountUtils::getAccountObjectByType($account[2]);
                    $object->setExternalId($account[0]);
                    $object->setUsername($account[1]);
                    $this->em->persist($object);
                }
                $this->em->flush();

                // Add new accounts to admin-user

                /** @var array $account */
                foreach ($newAccounts as $account) {
                    $class  = AccountUtils::getAccountClassByType($account[2]);
                    $repo   = $this->repos[$class];
                    $object = $repo->findOneBy(['externalId' => $account[0]]);
                    if ($object instanceof InstagramAccount) {
                        $user->addInstagramAccount($object);
                    }
                    if ($object instanceof VkAccount) {
                        $user->addVkAccount($object);
                    }
                    $this->em->persist($user);
                }
                $this->em->flush();

            } else {
                $output->writeln('Recording accounts data from database to file');

                $newAccounts = [];
                /** @var InstagramAccount|VkAccount $account */
                foreach ($accountsDB as $account) {
                    if (!AccountUtils::existInFile($accountsFile, $account)) {
                        $newAccounts[] = $account;
                    }
                }
                if (!$newAccounts) {
                    $output->writeln('New accounts not found!');
                    return;
                }
                $newAccounts = array_map(
                    function ($account) {
                        return [$account->getExternalId(), $account->getUsername(), AccountUtils::getAccountTypeByObject($account)];
                    },
                $newAccounts);
                $io->table(['id', 'username', 'type'], $newAccounts);

                // Add new accounts to file

                /** @var array $account */
                foreach ($newAccounts as $account) {
                    $accountsFile[] = $account;
                }
                FileUtils::putJsonData($accountsFile, FileUtils::ACCOUNTS_FILE);
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
     * @return array
     */
    private function getModes()
    {
        return [
            self::MODE_FROM_FILE,
            self::MODE_FROM_DB,
        ];
    }

    /**
     * Modify array accounts data from database to needed structure
     *
     * @param array $accounts
     *
     * @return array
     */
    private function modifyAccountsData(array $accounts)
    {
        $result = [];
        /** @var InstagramAccount|VkAccount $account */
        foreach ($accounts as $account) {
            $type = AccountUtils::getAccountTypeByObject($account);
            $result[$type][$account->getExternalId()] = $account;
        }
        return $result;
    }
}