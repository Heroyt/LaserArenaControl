import Game from '../../game/game';

export function validateForm(data: FormData, game : Game): boolean {
	console.log(data.get('action'));
	if (data.get('action') !== 'load') {
		return true;
	}

	const activePlayers = game.getActivePlayers();
	console.log(activePlayers);
	if (activePlayers.length < 2) {
		game.noPlayersTooltip.show();
		return false;
	}

	if (game.getModeType() === 'TEAM') {
		let ok = true;
		const disabledPlayers = activePlayers.filter(player => player.team === null);
		if ((activePlayers.length - disabledPlayers.length) < 2) {
			ok = false;
			disabledPlayers.forEach(player => {
				player.selectTeamTooltip.show();
			});
		}
		if (!ok) {
			return false;
		}
	}

	let ok = true;
	const activeTeams = game.getActiveTeams();
	if (game.getModeType() === 'TEAM' && activeTeams.length < 2) {
		game.atLeastTwoTeamsTooltip.show();
		return false;
	}
	activeTeams.forEach(team => {
		if (team.name.length < team.$name.minLength) {
			ok = false;
			team.emptyNameTooltip.show();
		} else if (team.name.length > team.$name.maxLength) {
			ok = false;
			team.nameTooLongTooltip.show();
		}
	});

	return ok;
}