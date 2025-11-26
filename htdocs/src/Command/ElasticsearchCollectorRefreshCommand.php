<?php

namespace App\Command;

use App\Service\ElasticsearchService;
use JACQ\Repository\Herbarinput\CollectorRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:elastic-collectors',
    description: 'Updates search index of collectors in the ElasticSearch engine (drop existing index and fill with actual data)')]
class ElasticsearchCollectorRefreshCommand extends Command
{
    public const string IndexName = "collector-test";
    public function __construct(
        readonly private CollectorRepository $repo,
        readonly private ElasticsearchService $elasticsearchService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $output->writeln("<info>Recreating index ". self::IndexName . "…</info>");
        $this->elasticsearchService->recreateIndex( self::IndexName);

        $batchSize = 1000;
        $bulk = [];
        $count = 0;
        $indexed = 0;

        foreach ($this->repo->iterateAll() as $row) {

            $bulk[] = json_encode([
                "index" => ["_index" => self::IndexName, "_id" => $row['id']]
            ]);

            $bulk[] = json_encode([
                "name" => $row['name'],
            ]);

            $count++;
            $indexed++;

            if ($count >= $batchSize) {
                $this->elasticsearchService->bulk($bulk);
                $bulk = [];
                $count = 0;

                $output->writeln("Indexed $indexed …");
            }
        }

        // flush last batch
        if (!empty($bulk)) {
            $this->elasticsearchService->bulk($bulk);
            $output->writeln("Indexed TOTAL $indexed records.");
        }

        return Command::SUCCESS;
    }
}

