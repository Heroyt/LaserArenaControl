/**
 * Information about one player
 */
export interface PlayerData {
    /**
     * Player HTML element
     */
    player: HTMLDivElement,
    /**
     * Animation length in milliseconds
     */
    length: number,
    /**
     * Real (final) position
     */
    position: number,
    /**
     * Real (final) score
     */
    score: number,
    /**
     * Animated score
     */
    currentScore: number,
    /**
     * Element that displays player's current position
     */
    positionEl: HTMLDivElement,
    /**
     * Element that displays player's current score - wrapper
     */
    scoreEl: HTMLDivElement,
    /**
     * Element that displays player's current score value
     */
    scoreValueEl: HTMLSpanElement,
    /**
     * If the player's animation is done
     */
    done: boolean,
    /**
     * Player's team index
     */
    team: string,
    /**
     * Optional information about player's ammo - for some game modes
     */
    ammo?: {
        /**
         * Starting ammo
         */
        start: number, /**
         * Remaining ammo
         */
        rest: number, /**
         * Animated value
         */
        current: number, /**
         * Wrapper that displays the value
         */
        el: HTMLSpanElement,
    },
    /**
     * Optional information about player's lives - for some game modes
     */
    lives?: {
        /**
         * Starting lives
         */
        start: number, /**
         * Remaining lives
         */
        rest: number, /**
         * Animated value
         */
        current: number, /**
         * Wrapper that displays the value
         */
        el: HTMLSpanElement,
    },
    /**
     * Optional information about player's accuracy - for some game modes
     */
    accuracy?: {
        /**
         * Animated value
         */
        current: number, /**
         * Real (final) value
         */
        value: number, /**
         * Circle radius (for svg angle calculation)
         */
        radius: number, /**
         * Value for drawing the SVG semicircle
         */
        secondDashArray: number, /**
         * Percentage SVG element
         */
        svgEl: SVGCircleElement, /**
         * Wrapper that displays the value
         */
        valueEl: SVGTSpanElement,
    },
}

/**
 * Highlight object that is returned from server
 */
export interface Highlight {
    type: string,
    score: number,
    value: string,
    description: string,
}

/**
 * Information about one team
 */
export interface TeamData {
    /**
     * Team HTML element
     */
    team: HTMLDivElement;
    /**
     * Element that displays team's current score - wrapper
     */
    scoreEl: HTMLDivElement;
    /**
     * Element that displays team's current score value
     */
    scoreValueEl: HTMLSpanElement;
    /**
     * Real (final) score
     */
    score: number;
    /**
     * Animated score
     */
    currentScore: number;
}