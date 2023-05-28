export default function initTournamentRozlos(): void {
	const form = document.getElementById('rozlos-form') as HTMLFormElement | undefined;
	if (!form) {
		return;
	}

	const teamCount = parseInt(form.dataset.teams);

	const tournamentType = document.getElementById('tournament-type') as HTMLSelectElement;
	const gameLength = document.getElementById('game-length') as HTMLInputElement;
	const gamePause = document.getElementById('game-pause') as HTMLInputElement;

	const tournamentGames = document.getElementById('tournament-games') as HTMLSpanElement;
	const tournamentLength = document.getElementById('tournament-length') as HTMLSpanElement;
	const tournamentGamesPerTeam = document.getElementById('tournament-games-per-team') as HTMLSpanElement;
	const tournamentTimePerTeam = document.getElementById('tournament-time-per-team') as HTMLSpanElement;

	recalculate();

	tournamentType.addEventListener('change', recalculate);
	gameLength.addEventListener('input', recalculate);
	gamePause.addEventListener('input', recalculate);

	function recalculate() {
		let games = 0;
		let gamesPerTeam = 0;
		switch (tournamentType.value) {
			case 'rr':
				games = teamCount * (teamCount - 1) / 2;
				gamesPerTeam = teamCount - 1;
				break;
			case '2grr':
			case '2grr10':
				const half = teamCount / 2;
				gamesPerTeam = (half - 1) * 2;
				games = (half * (half - 1) / 2) * 4;
				break;
		}

		const length = (games * parseInt(gameLength.value)) + ((games - 1) * parseInt(gamePause.value));
		const teamLength = gamesPerTeam * parseInt(gameLength.value);

		tournamentGames.innerText = games.toString();
		tournamentLength.innerText = `${Math.floor(length / 60)}h ${length % 60}min`;
		tournamentGamesPerTeam.innerText = gamesPerTeam.toString();
		tournamentTimePerTeam.innerText = `${Math.floor(teamLength / 60)}h ${teamLength % 60}min`;
	}
}