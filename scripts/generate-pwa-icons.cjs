const fs = require('node:fs');
const path = require('node:path');
const { PNG } = require('pngjs');

const projectRoot = path.resolve(__dirname, '..');
const sourcePath = path.join(projectRoot, 'public/images/logo.png');
const background = [244, 241, 232];

function sample(source, x, y) {
    const x0 = Math.max(0, Math.min(source.width - 1, Math.floor(x)));
    const y0 = Math.max(0, Math.min(source.height - 1, Math.floor(y)));
    const x1 = Math.min(source.width - 1, x0 + 1);
    const y1 = Math.min(source.height - 1, y0 + 1);
    const tx = x - Math.floor(x);
    const ty = y - Math.floor(y);
    const pixels = [[x0, y0, (1 - tx) * (1 - ty)], [x1, y0, tx * (1 - ty)], [x0, y1, (1 - tx) * ty], [x1, y1, tx * ty]];
    const rgba = [0, 0, 0, 0];

    for (const [pixelX, pixelY, weight] of pixels) {
        const offset = (source.width * pixelY + pixelX) << 2;
        for (let channel = 0; channel < 4; channel++) {
            rgba[channel] += source.data[offset + channel] * weight;
        }
    }

    return rgba;
}

function createIcon(source, size, scale = 1) {
    const output = new PNG({ width: size, height: size, colorType: 2 });
    const renderedSize = size * scale;
    const inset = (size - renderedSize) / 2;

    for (let y = 0; y < size; y++) {
        for (let x = 0; x < size; x++) {
            const outputOffset = (size * y + x) << 2;
            let red = background[0];
            let green = background[1];
            let blue = background[2];

            if (x >= inset && x < size - inset && y >= inset && y < size - inset) {
                const sourceX = ((x - inset + 0.5) / renderedSize) * source.width - 0.5;
                const sourceY = ((y - inset + 0.5) / renderedSize) * source.height - 0.5;
                const [sourceRed, sourceGreen, sourceBlue, sourceAlpha] = sample(source, sourceX, sourceY);
                const alpha = sourceAlpha / 255;
                red = sourceRed * alpha + red * (1 - alpha);
                green = sourceGreen * alpha + green * (1 - alpha);
                blue = sourceBlue * alpha + blue * (1 - alpha);
            }

            output.data[outputOffset] = Math.round(red);
            output.data[outputOffset + 1] = Math.round(green);
            output.data[outputOffset + 2] = Math.round(blue);
            output.data[outputOffset + 3] = 255;
        }
    }

    return output;
}

const source = PNG.sync.read(fs.readFileSync(sourcePath));
const icons = [
    ['apple-touch-icon.png', 180, 1],
    ['icon-192.png', 192, 1],
    ['icon-512.png', 512, 1],
    ['icon-maskable-512.png', 512, 0.8],
];

for (const [filename, size, scale] of icons) {
    const icon = createIcon(source, size, scale);
    const outputPath = path.join(projectRoot, 'public/images', filename);
    fs.writeFileSync(outputPath, PNG.sync.write(icon, { deflateLevel: 9 }));
    console.log(`Created ${path.relative(projectRoot, outputPath)} (${size}x${size})`);
}
