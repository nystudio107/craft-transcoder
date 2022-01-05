declare module "vue-confetti/src/confetti.js";

enum ParticlesType {
    circle = "circle",
    rect = "rect",
    heart = "heart",
    image = "image",
}

interface ParticlesConfig {
    type : ParticlesType,
    size : number,
    dropRate : number,
    colors : string[],
    url : string | null,
}

interface ConfettiConfig {
    particles : Partial<ParticlesConfig>[],
    defaultType : ParticlesType,
    defaultSize : number,
    defaultDropRate : number,
    defaultColors : string[],
    canvasId : number,
    particlesPerFrame : number,
}
