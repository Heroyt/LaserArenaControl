declare global {
	const features: {
		[index: string]: boolean
	};
}

/**
 * Check if a specific feature is enabled by the environment
 * @param feature {string} Feature name
 */
export function isFeatureEnabled(feature: string): boolean {
	feature = feature.toLocaleLowerCase();
	if (!(feature in features)) {
		return false;
	}
	return features[feature];
}