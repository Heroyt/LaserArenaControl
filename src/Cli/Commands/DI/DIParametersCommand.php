<?php

declare(strict_types=1);

namespace App\Cli\Commands\DI;

use App\Cli\ParametersSorter;
use Nette\DI\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function array_keys;
use function count;
use function get_class;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function next;
use function prev;
use function sprintf;

final class DIParametersCommand extends Command
{
    private Container $container;

    private bool $exportHint;

    /** @var array<mixed>|null */
    private ?array $parameters;

    /**
     * @param  array<mixed>|null  $parameters
     */
    public function __construct(Container $container, bool $exportHint, ?array $parameters = null) {
        parent::__construct();
        $this->container = $container;
        $this->exportHint = $exportHint;
        $this->parameters = $parameters;
    }

    public static function getDefaultName() : string {
        return 'di:parameters';
    }

    public static function getDefaultDescription() : string {
        return 'Show DI container parameters';
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int {
        $parameters = $this->parameters ?? $this->container->getParameters();

        if ($parameters === []) {
            $output->writeln('No parameters found in DI container.');

            if ($this->exportHint) {
                $output->writeln(
                  'Export of parameters into DIC is disabled.'.
                  " You may enable it for only this command by setting console extension option 'di > parameters > backup' to 'true'",
                );

                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        $this->printSortedParameters(
          $output,
          ParametersSorter::sortByType($parameters),
        );

        return self::SUCCESS;
    }

    /**
     * @param  array<mixed>  $parameters
     */
    private function printSortedParameters(OutputInterface $output, array $parameters, string $spaces = '  ') : void {
        $lastKey = array_keys($parameters)[count($parameters) - 1];

        foreach ($parameters as $key => $item) {
            if (is_array($item)) {
                if ($item === []) {
                    $output->writeln(
                      sprintf(
                        '%s<fg=cyan>%s</>: <fg=white>[]</>',
                        $spaces,
                        $key,
                      )
                    );

                    if ($key !== $lastKey) {
                        $output->writeln('');
                    }
                }
                else {
                    $output->writeln(
                      sprintf(
                        '%s<fg=cyan>%s</>:',
                        $spaces,
                        $key,
                      )
                    );

                    $this->printSortedParameters($output, $item, $spaces.'  ');
                }
            }
            else {
                $output->writeln(
                  sprintf(
                    '%s<fg=cyan>%s</>: %s',
                    $spaces,
                    $key,
                    $this->valueToString($item),
                  )
                );

                if ($key === $lastKey) {
                    $output->writeln('');
                }
                else if (is_array(next($parameters))) {
                    $output->writeln('');
                    prev($parameters);
                }
            }
        }
    }

    /**
     * @param  mixed  $value
     */
    private function valueToString($value) : string {
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
            $fg = 'yellow';
        }
        else if (is_int($value) || is_float($value)) {
            $fg = 'green';
        }
        else if (is_string($value)) {
            $fg = 'white';
        }
        else if ($value === null) {
            $value = 'null';
            $fg = 'yellow';
        }
        else {
            $value = get_class($value);
            $fg = 'red';
        }

        return "<fg={$fg}>{$value}</>";
    }
}
