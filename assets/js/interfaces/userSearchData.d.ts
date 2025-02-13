export interface UserSearchData {
	id: number;
	nickname: string;
	code: string;
	email: string;
	rank: number;
	birthday?: string | null;
	connections: { type: string, identifier: string }[];
}