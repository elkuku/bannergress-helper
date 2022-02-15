<?php
/**
 * Created by PhpStorm.
 * User: elkuku
 * Date: 11.10.18
 * Time: 10:32
 */

namespace App\Service;

use App\Entity\Waypoint;
use App\Type\AgentInfoType;
use App\Type\AgentLinkType;
use App\Type\InfoKeyPrepType;
use App\Type\InfoStepType;
use App\Type\MaxFieldType;
use App\Type\WayPointPrepType;
use DirectoryIterator;
use Exception;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * This is for https://github.com/tvwenger/maxfield
 */
class MaxFieldGenerator
{
    /**
     * @var string
     */
    protected string $rootDir = '';

    /**
     * @var int
     */
    private int $maxfieldVersion;

    /**
     * @var string
     */
    private string $maxfieldExec;

    /**
     * @var string
     */
    private string $googleApiKey;

    /**
     * @var string
     */
    private string $googleApiSecret;

    public function __construct(
        string $rootDir,
        string $maxfieldExec,
        int $maxfieldVersion,
        string $googleApiKey,
        string $googleApiSecret,
    ) {
        $this->rootDir = $rootDir.'/public/maxfields';

        // Path to makePlan.py
        $this->maxfieldExec = $maxfieldExec;
        $this->maxfieldVersion = $maxfieldVersion;
        $this->googleApiKey = $googleApiKey;
        $this->googleApiSecret = $googleApiSecret;
    }

    public function generate(
        string $projectName,
        string $wayPointList,
        int $playersNum,
        array $options
    ): void {
        $fileSystem = new Filesystem();

        try {
            $projectRoot = $this->rootDir.'/'.$projectName;
            $fileSystem->mkdir($projectRoot);
            $fileName = $projectRoot.'/'.$projectName.'.waypoints';
            $fileSystem->appendToFile($fileName, $wayPointList);

            if ($this->maxfieldVersion < 4) {
                $command = "python {$this->maxfieldExec} $fileName"
                    ." -d $projectRoot -f output.pkl -n $playersNum";
            } else {
                $command = "{$this->maxfieldExec} $fileName"
                    ." --outdir $projectRoot --num_agents $playersNum --output_csv"
                    ." --num_cpus 0 --num_field_iterations 100 --max_route_solutions 100";

                if ($this->googleApiKey) {
                    $command .= ' --google_api_key '.$this->googleApiKey;
                    // $command .= ' --google_api_secret '.$this->googleApiSecret;
                }

                if ($options['skip_plots']) {
                    $command .= " --skip_plots";
                }

                if ($options['skip_step_plots']) {
                    $command .= " --skip_step_plots";
                }

                $command .= " --verbose > $projectRoot/log.txt 2>&1";
            }

            $fileSystem->dumpFile($projectRoot.'/command.txt', $command);

            exec($command);
        } catch (IOExceptionInterface $exception) {
            echo 'An error occurred while creating your directory at '
                .$exception->getPath();
        } catch (Exception $exception) {
            echo $exception->getMessage();
        }
    }

    public function getContentList(string $item): array
    {
        $list = [];

        foreach (new DirectoryIterator($this->rootDir.'/'.$item) as $fileInfo) {
            if ($fileInfo->isFile()) {
                $list[] = $fileInfo->getFilename();
            }
        }

        sort($list);

        return $list;
    }

    public function getInfo(string $item): MaxFieldType
    {
        $info = new MaxFieldType();

        $numPlayers = preg_match('#([\d]+)pl-#', $item, $matches) ? $matches[1]
            : 1;

        if ($this->maxfieldVersion < 4) {
            $info->keyPrepTxt = $this->getTextFileContents(
                $item,
                'keyPrep.txt'
            );
            $info->keyPrep = $this->parseKeyPrepFile($info->keyPrepTxt);
            $info->ownershipPrep = $this->getTextFileContents(
                $item,
                'ownershipPrep.txt'
            );
            $info->agentsInfo = $this->getAgentsInfo($item, $numPlayers);
        } else {
            $info->keyPrepTxt = $this->getTextFileContents(
                $item,
                'key_preparation.csv'
            );
            $info->keyPrep = $this->parseKeyPrepFileCsv($info->keyPrepTxt);
            $info->ownershipPrep = $this->getTextFileContents(
                $item,
                'ownership_preparation.txt'
            );
            $info->agentsInfo = $this->getAgentsInfo2($item, $numPlayers);
        }

        $info->frames = $this->findFrames($item);
        $info->links = $this->parseCsvLinks($item);
        $info->steps = $this->calculateSteps($info->links);

        return $info;
    }

    /**
     * @return AgentInfoType[]
     */
    private function getAgentsInfo(string $item, int $numAgents = 1): array
    {
        $count = 1;
        $agentsInfo = [];

        try {
            start:
            $info = new AgentInfoType();
            $info->agentNumber = $count;
            $fileName = sprintf(
                'keys_for_agent_%d_of_%d.txt',
                $count,
                $numAgents
            );
            $info->keysInfo = $this->getTextFileContents($item, $fileName);
            $fileName = sprintf(
                'links_for_agent_%d_of_%d.txt',
                $count,
                $numAgents
            );
            $info->linksInfo = $this->getTextFileContents($item, $fileName);
            //            $info->links       = $this->parseLinksFile($info->linksInfo);
            $info->links = $this->parseCsvLinks($item);
            $info->keys = $this->parseCsvKeys($item);
            $agentsInfo[] = $info;
            $count++;
            goto start;
        } catch (FileNotFoundException $e) {
            // Finished.
        }

        return $agentsInfo;
    }

    /**
     * @return AgentInfoType[]
     */
    private function getAgentsInfo2(string $item, int $numAgents = 1): array
    {
        $count = 1;
        $agentsInfo = [];

        try {
            start:
            $info = new AgentInfoType();
            $info->agentNumber = $count;
            $fileName = sprintf('agent_%d_assignment.txt', $count);
            $info->linksInfo = $this->getTextFileContents($item, $fileName);
            $info->links = $this->parseCsvLinks($item);
            $info->keys = $this->parseCsvKeys($item);
            $agentsInfo[] = $info;
            $count++;
            goto start;
        } catch (FileNotFoundException $e) {
            // Finished.
        }

        return $agentsInfo;
    }

    public function getTextFileContents(
        string $fileName,
        string $item = ''
    ): string {
        $path = $item
            ? $this->rootDir.'/'.$item.'/'.$fileName
            : $this->rootDir.'/'.$fileName;

        if (false === file_exists($path)) {
            throw new FileNotFoundException('File not found.: '.$path);
        }

        return file_get_contents($path);
    }

    public function getList(): array
    {
        $list = [];

        foreach (new DirectoryIterator($this->rootDir) as $fileInfo) {
            if ($fileInfo->isDir() && !$fileInfo->isDot()) {
                $list[] = $fileInfo->getFilename();
            }
        }

        sort($list);

        return $list;
    }

    /**
     * @param Waypoint[] $wayPoints
     */
    public function convertWayPointsToMaxFields(array $wayPoints): string
    {
        $maxFields = [];

        foreach ($wayPoints as $wayPoint) {
            $points = $wayPoint->getLat().','.$wayPoint->getLon();
            $name = str_replace([';', '#'], '', $wayPoint->getName());
            $maxFields[] = $name.'; '.$_ENV['INTEL_URL']
                .'?ll='.$points.'&z=1&pll='.$points;
        }

        return implode("\n", $maxFields);
    }

    private function parseKeyPrepFile(string $contents): InfoKeyPrepType
    {
        $keyPrep = new InfoKeyPrepType();

        $lines = explode("\n", $contents);

        foreach ($lines as $line) {
            $l = trim($line);

            if (!$l || strpos($l, 'Keys Needed') === 0
                || strpos($l, 'Number of missing') === 0
            ) {
                continue;
            }

            $parts = explode('|', $l);

            if (4 !== \count($parts)) {
                continue;
            }

            $p = new WayPointPrepType();

            $p->keysNeeded = (int)$parts[0];
            $p->mapNo = (int)$parts[2];
            $p->name = trim($parts[3]);

            $keyPrep->addWayPoint($p);
        }

        return $keyPrep;
    }

    private function parseKeyPrepFileCsv(string $contents): InfoKeyPrepType
    {
        $keyPrep = new InfoKeyPrepType();

        $lines = explode("\n", $contents);

        foreach ($lines as $i => $line) {
            $l = trim($line);

            if (!$l
                || $i === 0
                || strpos($l, 'Keys Needed') === 0
                || strpos($l, 'Number of missing') === 0
            ) {
                continue;
            }

            $parts = explode(',', $l);

            if (5 !== \count($parts)) {
                continue;
            }

            $p = new WayPointPrepType();

            $p->keysNeeded = (int)$parts[0];
            $p->mapNo = (int)$parts[3];
            $p->name = trim($parts[4]);

            $keyPrep->addWayPoint($p);
        }

        return $keyPrep;
    }

    public function getMaxfieldVersion(): int
    {
        return $this->maxfieldVersion;
    }

    private function parseLinksFile(string $contents)
    {
        $lines = explode("\n", $contents);
        $link = null;
        $links = [];

        foreach ($lines as $line) {
            $l = trim($line);

            if (
                !$l
                || strpos($l, 'Complete link schedule') === 0
                || strpos($l, 'Links marked with') === 0
                || strpos($l, '----------') === 0
                || strpos($l, 'Minutes') === 0
                || strpos($l, 'Total') === 0
                || strpos($l, 'AP') === 0
                || strpos($l, 'Distance') === 0
                || strpos($l, 'Link') === 0
                || strpos($l, 'Fields') === 0
            ) {
                continue;
            }

            if (preg_match(
                '/(\d+)(\*)?\s+____1\s+(\d+)\s+([\w|\s]+)/',
                $l,
                $matches
            )
            ) {
                $link = new AgentLinkType();

                $link->linkNum = $matches[1];
                $link->isEarly = '*' === $matches[2];
                $link->originNum = $matches[3];
                $link->originName = $matches[4];
            } elseif (preg_match('/(\d+)\s+([\w|\s]+)/', $l, $matches)) {
                if (!$link) {
                    throw new Exception('Parse error in links file');
                }

                $link->destinationNum = $matches[1];
                $link->destinationName = $matches[2];

                $links[] = $link;
            }
        }

        return $links;
    }

    public function getImagePath(string $item, string $image): string
    {
        return $this->rootDir."/$item/$image";
    }

    public function remove(string $item): void
    {
        $fileSystem = new Filesystem();

        $fileSystem->remove($this->rootDir."/$item");
    }

    private function findFrames(string $item): int
    {
        $path = $this->rootDir.'/'.$item.'/frames';
        $frames = 0;

        if (false === file_exists($path)) {
            return $frames;
        }

        foreach (new \DirectoryIterator($path) as $file) {
            if (preg_match(
                '/frame_(\d\d\d\d\d)/',
                $file->getFilename(),
                $matches
            )
            ) {
                $x = (int)$matches[1];
                $frames = $x > $frames ? $x : $frames;
            }
        }

        return $frames;
    }

    private function parseCsvLinks(string $item): array
    {
        $links = [];

        if ($this->maxfieldVersion < 4) {
            $contents = $this->getTextFileContents(
                $item,
                'links_for_agents.csv'
            );
        } else {
            $contents = $this->getTextFileContents(
                $item,
                'agent_assignments.csv'
            );
        }

        $lines = explode("\n", $contents);

        foreach ($lines as $i => $line) {
            if (0 === $i || !$line) {
                continue;
            }

            $parts = explode(',', $line);

            if (6 !== \count($parts)) {
                throw new \UnexpectedValueException('Error parsing CSV file');
            }

            $link = new AgentLinkType();

            $link->linkNum = (int)$parts[0];
            $link->isEarly = (bool)strpos($parts[0], '*');
            $link->agentNum = (int)$parts[1];
            $link->originNum = (int)$parts[2];
            $link->originName = trim($parts[3]);
            $link->destinationNum = (int)$parts[4];
            $link->destinationName = trim($parts[5]);

            $links[] = $link;
        }

        usort(
            $links,
            function ($a, $b) {
                return $a->linkNum > $b->linkNum;
            }
        );

        return $links;
    }

    private function parseCsvKeys(string $item): InfoKeyPrepType
    {
        $keyInfo = new InfoKeyPrepType();

        if ($this->maxfieldVersion < 4) {
            $contents = $this->getTextFileContents(
                $item,
                'keys_for_agents.csv'
            );
        } else {
            $contents = $this->getTextFileContents(
                $item,
                'agent_key_preparation.csv'
            );
        }

        $lines = explode("\n", $contents);

        foreach ($lines as $i => $line) {
            if (0 === $i || !$line) {
                continue;
            }

            $parts = explode(',', $line);

            if (4 !== \count($parts)) {
                throw new \UnexpectedValueException('Error parsing CSV file');
            }

            $wayPoint = new WayPointPrepType();

            if ($this->maxfieldVersion < 4) {
                $wayPoint->agentNum = (int)$parts[0];
                $wayPoint->mapNo = (int)$parts[1];
                $wayPoint->name = trim($parts[2]);
                $wayPoint->keysNeeded = (int)$parts[3];
            } else {
                $wayPoint->agentNum = (int)$parts[0];
                $wayPoint->keysNeeded = (int)$parts[1];
                $wayPoint->mapNo = (int)$parts[2];
                $wayPoint->name = trim($parts[3]);
            }

            $keyInfo->addWayPoint($wayPoint);
        }

        return $keyInfo;
    }

    private function calculateSteps(array $links): array
    {
        $steps = [];

        foreach ($links as $i => $link) {
            if (($i > 0) && $link->originNum !== $links[$i - 1]->originNum) {
                $step = new InfoStepType();
                $step->action = InfoStepType::TYPE_MOVE;
                $step->agentNum = $link->agentNum;
                $step->originNum = $links[$i - 1]->originNum;
                $step->originName = $links[$i - 1]->originName;
                $step->destinationNum = $link->originNum;
                $step->destinationName = $link->originName;

                $steps[] = $step;
            }

            $step = new InfoStepType();

            $step->action = InfoStepType::TYPE_LINK;

            $step->linkNum = $link->linkNum;

            $step->agentNum = $link->agentNum;
            $step->originNum = $link->originNum;
            $step->originName = $link->originName;
            $step->destinationNum = $link->destinationNum;
            $step->destinationName = $link->destinationName;

            $steps[] = $step;
        }

        return $steps;
    }
}
