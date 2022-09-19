export interface PhpDateTime {
	date: string,
	timezone_type: number,
	timezone: string
}

export interface PlayerData {
	id?: number,
	name: string,
	vip?: boolean,
	score?: number,
	skill: number,
	vest?: number,
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
	fileName?: string,
	order?: number
}

export interface GameData {
	id?: number,
	code?: string,
	fileNumber?: number | string,
	playerCount: number,
	fileTime?: PhpDateTime,
	start?: PhpDateTime,
	end?: PhpDateTime,
	mode: { id: number, name?: string, description?: string, type?: 'TEAM' | 'SOLO' },
	players: { [index: string]: PlayerData },
	teams: { [index: string]: TeamData },
	music: MusicMode | null
}