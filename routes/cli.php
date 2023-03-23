<?php
/**
 * @file  cli.php
 * @brief CLI route definitions
 */

use App\Cli\Colors;
use App\Cli\Enums\ForegroundColors;
use App\Controllers\Cli\EventServer;
use App\Controllers\Cli\Games;
use App\Controllers\Cli\Help;
use App\Controllers\Cli\Regression;
use App\Controllers\Cli\Translations;
use App\Controllers\Cli\Version;
use Lsr\Core\Routing\CliRoute;
use Lsr\Helpers\Cli\CliHelper;

if (PHP_SAPI === 'cli') {

	CliRoute::cli('list', [Help::class, 'listCommands'])
					->description('Lists all available CLI commands.')
					->usage('[commandGroup]')
					->addArgument([
													'name'        => 'commandGroup',
													'isOptional'  => true,
													'description' => 'Optional filter for command groups',
												])
					->help(
						static function() {
							echo Colors::color(ForegroundColors::LIGHT_PURPLE).lang('Examples', context: 'cli.messages').':'.Colors::reset().PHP_EOL;
							echo Colors::color(ForegroundColors::LIGHT_BLUE).CliHelper::getCaller().' list'.Colors::reset().PHP_EOL."\tLists all available commands.".PHP_EOL;
							echo Colors::color(ForegroundColors::LIGHT_BLUE).CliHelper::getCaller().' list results'.Colors::reset().PHP_EOL."\tLists all available commands from the 'results' group (starting with 'results/').".PHP_EOL;
						}
					);

	CliRoute::cli('help', [Help::class, 'help'])
					->description('Print help for a command.')
					->usage('<command>')
					->addArgument([
													'name'        => 'command',
													'isOptional'  => false,
													'description' => 'A command to get information about.',
													'suggestions' => [
														'autocomplete/get',
														'list',
														'help',
														'results/load',
														'event/server',
													],
												])
					->help(
						static function() {
							Colors::color(ForegroundColors::LIGHT_PURPLE).lang('Examples', context: 'cli.messages').':'.Colors::reset().PHP_EOL;
							echo Colors::color(ForegroundColors::LIGHT_BLUE).CliHelper::getCaller().' help results/load'.Colors::reset().PHP_EOL."\tPrints out information about the command '".Colors::color(ForegroundColors::LIGHT_BLUE)."results/load".Colors::reset()."'".PHP_EOL;
						}
					);

	CliRoute::cli('results/load', [Games::class, 'import'])
					->description('Imports all results from a given directory.')
					->usage('<dir>')
					->addArgument([
													'name'        => 'dir',
													'isOptional'  => false,
													'description' => 'A valid results directory',
												])
					->help(static function() {
						echo Colors::color(ForegroundColors::LIGHT_PURPLE).lang('Examples', context: 'cli.messages').':'.Colors::reset().PHP_EOL;
						echo Colors::color(ForegroundColors::LIGHT_BLUE).CliHelper::getCaller().' results/load results'.Colors::reset().PHP_EOL."\tWill import all new files from the '".Colors::color(ForegroundColors::LIGHT_BLUE)."./results/".Colors::reset()."' directory".PHP_EOL;
					});

	CliRoute::cli('games/sync', [Games::class, 'sync'])
					->description('Synchronize not synchronized games from DB to public API.')
					->usage('[limit=10] [timeout]')
					->addArgument([
													'name'        => 'limit',
													'isOptional'  => true,
													'description' => 'Maximum number of games to synchronize',
												],
												[
													'name'        => 'timeout',
													'isOptional'  => true,
													'description' => 'Timeout for each curl request',
												])
					->help(static function() {
						echo Colors::color(ForegroundColors::LIGHT_PURPLE).lang('Examples', context: 'cli.messages').':'.Colors::reset().PHP_EOL;
						echo Colors::color(ForegroundColors::LIGHT_BLUE).CliHelper::getCaller().' results/load results'.Colors::reset().PHP_EOL."\tWill import all new files from the '".Colors::color(ForegroundColors::LIGHT_BLUE)."./results/".Colors::reset()."' directory".PHP_EOL;
					});

	CliRoute::cli('event/server', [EventServer::class, 'start'])
					->description('Start a WebSocket event server.')
					->help(static function() {
						echo Colors::color(ForegroundColors::LIGHT_PURPLE).lang('Information', context: 'cli.messages').':'.Colors::reset().PHP_EOL;
						echo "Upon running this command a WebSocket server will be started listening on port '".Colors::color(ForegroundColors::LIGHT_BLUE)."EVENT_PORT".Colors::reset()."' (".Colors::color(ForegroundColors::LIGHT_BLUE).EVENT_PORT.Colors::reset()."). ";
						echo "This server allows for connections from any source.".PHP_EOL;
						echo "It's main purpose is to broadcast events that are either send by any of the connected clients through a WebSocket connection, or pooled from a database, where any PHP process can save messages to.".PHP_EOL;
						echo "Messages are simple strings. They do not carry any data. Their purpose is to inform the front-end client, that some event has occurred and it should respond accordingly.".PHP_EOL;
					});

	CliRoute::cli('autocomplete/get', [Help::class, 'generateAutocompleteJson'])
					->description('Generate an autocomplete JSON for all available commands.')
					->usage('[out]')
					->addArgument([
													'name'        => 'out',
													'isOptional'  => true,
													'description' => 'If set, output will be written to the [out] file. Otherwise, output will be written to stdout.',
													'template'    => 'filepaths',
												]);

	CliRoute::cli('translations/compile', [Translations::class, 'compile'])
					->description('Compile all translation files.');

	CliRoute::cli('translations/removeComments', [Translations::class, 'removeComments'])
					->description('Remove all comments from translation files.');

	CliRoute::cli('translations/merge', [Translations::class, 'merge'])
					->description('Merge translations from this and one other project.')
					->usage('<dir> [contextSkip]')
					->addArgument([
													'name'        => 'dir',
													'isOptional'  => false,
													'description' => 'A language directory from the other project.',
													'template'    => 'filepaths',
												],
												[
													'name'        => 'contextSkip',
													'isOptional'  => true,
													'description' => 'A comma separated list of context names to skip while merging.',
												]);

	CliRoute::cli('translations/deleteByContext', [Translations::class, 'removeContext'])
					->description('Remove all translations from a specified context.')
					->usage('<context>')
					->addArgument(
						[
							'name'        => 'context',
							'isOptional'  => true,
							'description' => 'A comma separated list of context names to skip while merging.',
						]);

	CliRoute::cli('regression/hits', [Regression::class, 'calculateHitRegression'])
					->description('Calculate regression for player\'s hits.')
					->usage('[TEAM|SOLO]')
					->addArgument(
						[
							'name'        => 'type',
							'isOptional'  => true,
							'description' => 'Game type to calculate. Only "TEAM" or "SOLO" values are accepted. Default: "TEAM"',
						]);

	CliRoute::cli('regression/deaths', [Regression::class, 'calculateDeathRegression'])
					->description('Calculate regression for player\'s deaths.')
					->usage('[TEAM|SOLO]')
					->addArgument(
						[
							'name'        => 'type',
							'isOptional'  => true,
							'description' => 'Game type to calculate. Only "TEAM" or "SOLO" values are accepted. Default: "TEAM"',
						]);

	CliRoute::cli('regression/hitsOwn', [Regression::class, 'calculateHitOwnRegression'])
					->description('Calculate regression for player\'s teammate hits.');

	CliRoute::cli('regression/deathsOwn', [Regression::class, 'calculateDeathOwnRegression'])
					->description('Calculate regression for player\'s teammate deaths.');

	CliRoute::cli('regression/updateAll', [Regression::class, 'updateRegressionModels'])
					->description('Recalculate and save all regression models.');

	CliRoute::cli('version', [Version::class, 'getVersion'])
					->description('Get current App version.');

	CliRoute::cli('version/list', [Version::class, 'list'])
					->description('List all versions.');

	CliRoute::cli('version/checkUpdate', [Version::class, 'isUpdateAvailable'])
					->description('Checks if update is available.');
}