// @ts-ignore
import * as jscolor from "@eastdesire/jscolor";

jscolor.presets.default = {
    format: 'hex',
    uppercase: false,
};

export default async function initJsColor() {
    jscolor.install();
}