export interface UserSearchData {
	id: number;
	nickname: string;
	code: string;
	email: string;
	rank: number;
	connections: { type: string, identifier: string }[];
}