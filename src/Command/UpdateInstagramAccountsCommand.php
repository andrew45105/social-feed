<?php

namespace App\Command;

use App\Entity\InstagramAccount;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Class UpdateInstagramAccountsCommand
 * @package App\Command
 */
class UpdateInstagramAccountsCommand extends Command
{
    protected static $defaultName = 'app:update-instagram-accounts';

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var ParameterBagInterface
     */
    private $params;

    /**
     * UpdateInstagramAccountsCommand constructor.
     * @param EntityManagerInterface $em
     * @param ParameterBagInterface $params
     */
    public function __construct(EntityManagerInterface $em, ParameterBagInterface $params)
    {
        parent::__construct();
        $this->em       = $em;
        $this->params   = $params;
    }

    /**
     * @{@inheritdoc}
     */
    protected function configure()
    {
        $this->setDescription('Setting actual instagram usernames');
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
            $usernameRoute  = $this->params->get('app.instagram_username_route');
            $userAgent      = $this->params->get('app.instagram_useragent');

            // Found needed accounts
            $accounts = $this->em->getRepository(InstagramAccount::class)->findBy(['needUpdate' => true]);

            $accountsData = [];

            /** @var InstagramAccount $account */
            foreach ($accounts as $account) {
                $accountsData[$account->getExternalId()] = $account;
            }

            $client = new Client();

            $promises = [];

            // Making async requests
            foreach (array_keys($accountsData) as $externalId) {
                $promises[$externalId] = $client->getAsync(sprintf($usernameRoute, $externalId), [
                    'headers' => [
                        'User-Agent' => $userAgent,
                    ]
                ]);
            }

            $results = Promise\settle($promises)->wait();

            foreach ($results as $accountId => $result) {

                $date = '[' . date('Y-m-d h:i:s') . ']';

                if ($result['state'] === 'fulfilled') {
                    /** @var Response $response */
                    $response = $result['value'];
                    if ($response->getStatusCode() == 200) {
                        $usernameData = json_decode($response->getBody()->getContents(), true);
                        $actualUsername = $usernameData['user']['username'] ?? null;
                        if ($actualUsername) {
                            $io->success($date . " SUCCESS: actual name: $actualUsername, account $accountId");
                            // Setting new username
                            $account = $accountsData[$accountId];
                            $account->setUsername($actualUsername);
                            $account->setNeedUpdate(false);
                            $this->em->persist($account);
                            $this->em->flush();
                        } else {
                            $io->error($date . " ERR: no actual name for $accountId (" . json_encode($usernameData) . ')');
                        }
                    } else {
                        $io->error($date . ' ERR: status code ' . $response->getStatusCode());
                    }
                } else if ($result['state'] === 'rejected') {
                    $io->error($date . ' ERR rejected: ' . $result['reason']);
                } else {
                    $io->error($date . ' ERR: unknown exception ');
                }
            }

        } catch (\Throwable $e) {
            $io->error(
                'An unexpected error occurred'.PHP_EOL.PHP_EOL.
                $e->getMessage()
            );
        }
    }
}