<?php

namespace Airam\Installer;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use RuntimeException;

class NewCommand extends Command
{
    private $action = "create-project";
    private $projectname = "egarciahz/airam";

    /**
     * @return void
     */
    public function configure()
    {
        $this->setName("new")
            ->setDescription("Create a new Airam Project")
            ->addArgument("name", InputArgument::REQUIRED, "Name of project folder")
            ->addOption('dev', 'D', InputOption::VALUE_NONE, 'Installs the latest "development" release')
            ->addOption('no-npm', 'N', InputOption::VALUE_OPTIONAL, 'Not install Npm packages')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Forces install even if the directory already exists');
    }

    /**
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return int
     */
    public function execute(InputInterface  $input, OutputInterface  $output)
    {
        $this->printBanner($output);
        sleep(1);

        $name = $input->getArgument('name');
        $directory = $name && $name !== '.' ? getcwd() . '/' . $name : '.';
        $version = $this->getVersion($input);
        $composer = $this->findComposer();

        if (!$input->getOption('force')) {
            $this->checkDirectory($directory);
        }

        $commands = [
            $composer . " {$this->action} {$this->projectname} \"{$directory}\" {$version} --remove-vcs --prefer-dist",
        ];

        if ($directory != '.' && $input->getOption('force')) {
            if (is_dir($directory) || file_exists($directory)) {
                if (PHP_OS_FAMILY == 'Windows') {
                    array_unshift($commands, "rd /s /q \"$directory\"");
                } else {
                    array_unshift($commands, "rm -rf \"$directory\"");
                }

                array_unshift($commands, "chmod 775 -R \"$directory\"");
            }
        }

        if ($input->getOption('quiet')) {
            $commands = array_map(function ($value) {
                return $value . ' --quiet';
            }, $commands);
        }

        if (!$input->getOption('no-npm')) {
            // exec npm install packages command
            if (PHP_OS_FAMILY !== 'Windows') {
                if ($name && $name !== '.') {
                    array_push($commands,  "cd  \"{$directory}\"");
                }

                array_push($commands, "npm install", "npm run build");
            }
        }

        $process = Process::fromShellCommandline(implode(' && ', $commands), null, null, null, null);
        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            try {
                $process->setTty(true);
            } catch (RuntimeException $e) {
                $output->writeln('Warning: ' . $e->getMessage());
            }
        }

        $process->run(function ($type, $line) use ($output) {
            $output->write('    ' . $line);
        });

        if ($process->isSuccessful()) {
            if ($name && $name !== '.') {
                $this->replaceInFile(
                    'PAGE_TITLE=Airam',
                    'PAGE_TITLE=' . $name,
                    $directory . '/.env'
                );
            }

            $message = (PHP_OS_FAMILY == 'Windows') ? "Please install npm packages manually." : "";
            $output->writeln(PHP_EOL . "<comment>Application ready! {$message}</comment>");
        }

        return $process->getExitCode();
    }

    /**
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     */
    protected function printBanner(OutputInterface $output)
    {
        $airam = [
            '<fg=blue>
    ___      
   / _ \     _   _ _  ___ _    ___  __
  / /_\ \   | | | `/ | __` |  /   \/  \
 /  ___  \  | | | |  | \_| \  | |\_/| |
/_/     \_\ |_| |_|   \__/\_\ |_|   |_|</>
',
            '<fg=blue>A php Framework</>',
            PHP_EOL
        ];
        $output->write(join(PHP_EOL, $airam));
    }

    /**
     * check if folder exist
     *
     * @param  string  $directory
     * @return void
     */
    private function checkDirectory(string $directory)
    {
        if ((is_dir($directory) || is_file($directory)) && $directory != getcwd()) {
            throw new RuntimeException('Application already exists!');
        }
    }

    /**
     * Get the version that should be downloaded.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @return string
     */
    protected function getVersion(InputInterface $input)
    {
        if ($input->getOption('dev')) {
            return 'dev-development';
        }

        return 'dev-master';
    }

    /**
     * Replace the given string in the given file.
     *
     * @param  string  $search
     * @param  string  $replace
     * @param  string  $file
     * @return string
     */
    protected function replaceInFile(string $search, string $replace, string $file)
    {
        file_put_contents(
            $file,
            str_replace($search, $replace, file_get_contents($file))
        );
    }

    /**
     * Get the composer command for the environment.
     *
     * @return string
     */
    protected function findComposer()
    {
        $composerPath = getcwd() . '/composer.phar';

        if (file_exists($composerPath)) {
            return '"' . PHP_BINARY . '" ' . $composerPath;
        }

        return 'composer';
    }
}
