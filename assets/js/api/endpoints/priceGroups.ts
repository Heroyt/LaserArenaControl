import {fetchDelete, fetchGet, fetchPost, fetchPut} from '../../includes/apiClient';
import {PriceGroup} from '../../interfaces/gameInterfaces';

export async function getPriceGroups(): Promise<PriceGroup[]> {
	return fetchGet(`/api/pricegroups`, null, {'Accept': 'application/json'});
}

export async function createPriceGroup(name: string, price: number): Promise<PriceGroup> {
	return fetchPost(`/api/pricegroups`, {name, price}, {'Accept': 'application/json'});
}

export async function getPriceGroup(id: number): Promise<PriceGroup> {
	return fetchGet(`/api/pricegroups/${id}`, null, {'Accept': 'application/json'});
}

export async function deletePriceGroup(id: number): Promise<string> {
	return fetchDelete(`/api/pricegroups/${id}`, null, {'Accept': 'application/json'});
}

export type PriceGroupPartial = {
	name?: string,
	price?: number
};

export async function updatePriceGroup(id: number, data: PriceGroup | PriceGroupPartial): Promise<PriceGroup> {
	return fetchPut(`/api/pricegroups/${id}`, data, {'Accept': 'application/json'});
}