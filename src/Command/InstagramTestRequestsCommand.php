<?php

namespace App\Command;

use App\Utils\FileUtils;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;

/**
 * Class InstagramTestRequestsCommand
 * @package App\Command
 */
class InstagramTestRequestsCommand extends Command
{
    protected static $defaultName = 'app:instagram-test-requests';

    /**
     * @var string
     */
    private $baseRoute;

    /**
     * InstagramTestRequestsCommand constructor.
     * @param string $instagramBaseRoute
     */
    public function __construct(string $instagramBaseRoute)
    {
        parent::__construct();
        $this->baseRoute = $instagramBaseRoute;
    }

    /**
     * @{@inheritdoc}
     */
    protected function configure()
    {
        $this->setDescription('Tests requests to instagram userdata route');
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
            $client = new Client(['base_uri' => $this->baseRoute]);

            // Get 3 example usernames from file
            $usernames = FileUtils::getUsernamesExample(3);

            // Making async requests
            $promises = [
                $usernames[0]   => $client->getAsync("/$usernames[0]/?__a=1"),
                $usernames[1]   => $client->getAsync("/$usernames[1]/?__a=1"),
                $usernames[2]   => $client->getAsync("/$usernames[2]/?__a=1"),
                'wrong_user'    => $client->getAsync("/somewrongusername121243545665656546462222254/?__a=1")
            ];

            $results = Promise\settle($promises)->wait();

            foreach ($results as $domain => $result) {

                if ($result['state'] === 'fulfilled') {
                    /** @var Response $response */
                    $response = $result['value'];
                    if ($response->getStatusCode() == 200) {
                        dump($response->getBody()->getContents());
                    } else {
                        dump($response->getStatusCode());
                    }
                } else if ($result['state'] === 'rejected') {
                    dump('ERR rejected: ' . $result['reason']);
                } else {
                    dump('ERR: unknown exception ');
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