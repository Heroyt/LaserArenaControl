<?php
declare(strict_types=1);

namespace App\CQRS\CommandHandlers;

use App\CQRS\CommandResponses\ClearGroupsCommandResponse;
use App\CQRS\Commands\ClearGroupsCommand;
use App\Models\GameGroup;
use DateTimeImmutable;
use Lsr\CQRS\CommandHandlerInterface;
use Lsr\CQRS\CommandInterface;
use Lsr\Db\DB;
use Lsr\Logging\Logger;

class ClearGroupsCommandHandler implements CommandHandlerInterface
{

    /**
     * @param  ClearGroupsCommand  $command
     */
    public function handle(CommandInterface $command) : ClearGroupsCommandResponse {
        assert($command instanceof ClearGroupsCommand);
        $logger = new Logger(LOG_DIR, 'clear_groups');
        $logger->info('Clearing groups...');
        $response = new ClearGroupsCommandResponse();

        $groups = DB::select(GameGroup::TABLE, '*')
                    ->where('[active] = 1')
                    ->fetchIterator(false);
        $hourAgo = new DateTimeImmutable('-1 hour');
        $twoDaysAgo = new DateTimeImmutable('-2 days');
        foreach ($groups as $row) {
            $group = GameGroup::get($row->id_group, $row);
            // Filter out groups that were created in the last hour
            if ($group->createdAt !== null && $group->createdAt > $hourAgo) {
                continue;
            }
            // Check if group has games
            if (count($group->games) === 0) {
                // Delete groups without games
                if (!$group->delete()) {
                    $logger->error('Failed to delete group: '.$group->id);
                    continue;
                }
                $response->deleted++;
            }
            // Check if group's last game is older than 2 days.
            $lastDate = new DateTimeImmutable('1970-01-01');
            foreach ($group->games as $game) {
                if ($game->start > $lastDate) {
                    $lastDate = $game->start;
                }
            }
            if ($lastDate < $twoDaysAgo) {
                // Hide groups with games older than 2 days
                $group->active = false;
                if (!$group->save()) {
                    $logger->error('Failed to hide group: '.$group->id);
                    continue;
                }
                $response->hidden++;
            }
        }
        GameGroup::clearModelCache();
        $logger->info('Cleared groups: '.$response->deleted.' deleted, '.$response->hidden.' hidden');

        return $response;
    }
}