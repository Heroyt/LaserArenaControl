<?php
declare(strict_types=1);

namespace App\CQRS\CommandHandlers;

use App\CQRS\CommandResponses\ClearGroupsCommandResponse;
use App\CQRS\Commands\ClearGroupsCommand;
use App\Models\GameGroup;
use DateTimeImmutable;
use Lsr\CQRS\CommandHandlerInterface;
use Lsr\CQRS\CommandInterface;

class ClearGroupsCommandHandler implements CommandHandlerInterface
{

    /**
     * @param  ClearGroupsCommand  $command
     */
    public function handle(CommandInterface $command) : ClearGroupsCommandResponse {
        assert($command instanceof ClearGroupsCommand);
        $response = new ClearGroupsCommandResponse();

        $groups = GameGroup::getActive();
        $hourAgo = new DateTimeImmutable('-1 hour');
        $twoDaysAgo = new DateTimeImmutable('-2 days');
        foreach ($groups as $group) {
            // Filter out groups that were created in the last hour
            if ($group->createdAt !== null && $group->createdAt > $hourAgo) {
                continue;
            }
            // Check if group has games
            if (count($group->games) === 0) {
                // Delete groups without games
                $group->delete();
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
                $group->save();
                $response->hidden++;
            }
        }
        GameGroup::clearModelCache();

        return $response;
    }
}