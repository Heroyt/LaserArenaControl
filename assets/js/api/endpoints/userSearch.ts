import {UserSearchData} from "../../interfaces/userSearchData";
import {fetchGet} from "../../includes/apiClient";

export type UserSearchResponse = UserSearchData[];

export async function findUsers(search: string, noMail: boolean = false): Promise<UserSearchResponse> {
    const searchParams = new URLSearchParams({search});
    if (noMail) {
        searchParams.append('nomail', '1');
    }
    return fetchGet('/players/find', searchParams);
}

export async function findUsersPublic(search: string, noMail: boolean = false): Promise<UserSearchResponse> {
    const searchParams = new URLSearchParams({search});
    if (noMail) {
        searchParams.append('nomail', '1');
    }
    return fetchGet('/players/public/find', searchParams);
}