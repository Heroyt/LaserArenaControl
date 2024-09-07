export interface PhpDateTime {
	date: string,
	timezone_type: number,
	timezone: string
}

export interface PlayerPayInfo {
	playerName: string,
	gamesPlayed: number,
	gamesPaid: number,
	priceGroupId: number | null,
}

export interface PriceGroup {
	id: number,
	name: string,
	price: number,
	deleted?: boolean,
}

export interface PlayerData {
	id?: number,
	name: string,
	code?: string,
	vip?: boolean,
	score?: number,
	skill: number,
	avgSkill?: number,
	vest?: number | string,
	position?: number,
	accuracy?: number,
	hits?: number,
	deaths?: number,
	shots?: number,
	teamNum?: number,
	color?: number
}

export interface TeamData {
	id?: number,
	name: string,
	score?: number,
	color?: number,
	playerCount?: number,
	position?: number
}

export interface MusicMode {
	id: number,
	name?: string,
	group?: string,
	fileName?: string,
	order?: number
}

export interface ModeData {
	id: number | null,
	name?: string,
	description?: string,
	type?: 'TEAM' | 'SOLO',
	variations?: { [key: string]: string }
}

export interface GameData {
	id?: number,
	code?: string,
	fileNumber?: number | string,
	playerCount: number,
	fileTime?: string,
	start?: string,
	end?: string,
	mode: ModeData,
	players: { [index: string]: PlayerData },
	teams: { [index: string]: TeamData },
	music: MusicMode | null,
	playlist?: number,
	group?: GameGroupDataBase,
	table?: TableData,
	system?: string,
}

interface ModeSettings {
	public: boolean,
	mines: boolean,
	partWin: boolean,
	partTeams: boolean,
	partPlayers: boolean,
	partHits: boolean,
	partBest: boolean,
	partBestDay: boolean,
	playerScore: boolean,
	playerShots: boolean,
	playerMiss: boolean,
	playerAccuracy: boolean,
	playerMines: boolean,
	playerPlayers: boolean,
	playerPlayersTeams: boolean,
	playerKd: boolean,
	playerFavourites: boolean,
	playerLives: boolean,
	teamScore: boolean,
	teamAccuracy: boolean,
	teamShots: boolean,
	teamHits: boolean,
	teamZakladny: boolean,
	bestScore: boolean,
	bestHits: boolean,
	bestDeaths: boolean,
	bestAccuracy: boolean,
	bestHitsOwn: boolean,
	bestDeathsOwn: boolean,
	bestShots: boolean,
	bestMiss: boolean,
	bestMines: boolean,

	[index: string]: boolean,
}

export interface PlayerGroupData extends PlayerData {
	asciiName: string,
	gameCodes: string[],
}

interface GameGroupDataBase {
	id: number,
	name: string,
	active?: boolean,
	table?: TableData | null,
}

interface GameGroupData extends GameGroupDataBase {
	players?: { [index: string]: PlayerGroupData },
	teams?: {
		[index: string]: {
			id: string,
			name: string,
			system: string,
			color: number,
			players: { [index: string]: PlayerGroupData }
		}
	},
	meta?: {
		payment: { [index: string]: PlayerPayInfo }
	}
}

interface TableData {
	id: number,
	name: string,
	group?: GameGroupData | null,
	grid?: {
		row: number,
		col: number,
		width: number,
		height: number,
	}
}

interface GameMode {
	id: number,
	name: string,
	description: string | null,
	type: 'TEAM' | 'SOLO',
	loadName: string
	settings: ModeSettings,
}

interface Variation {
	id: number,
	name: string,
	public: boolean,
}

interface VariationsValue {
	variation: Variation,
	mode: GameMode,
	value: string,
	suffix: string,
	order: number,
}

interface VariationCollection {
	[index: number]: {
		variation: Variation,
		values: VariationsValue[]
	};
}

interface Music {
	id: number,
	name: string,
	fileName: string,
	media: string
}