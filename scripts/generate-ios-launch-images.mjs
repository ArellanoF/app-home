import { deflateSync, inflateSync } from 'node:zlib';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';

const sizes = [
    [640, 1136], [750, 1334], [828, 1792], [1080, 2340], [1125, 2436],
    [1170, 2532], [1179, 2556], [1206, 2622], [1242, 2688], [1284, 2778],
    [1290, 2796], [1320, 2868],
];
const outputDirectory = new URL('../public/images/launch/', import.meta.url);
const pixel = [244, 241, 232];
const logo = decodePng(readFileSync(new URL('../public/images/logo.png', import.meta.url)));

function crc32(buffer) {
    let crc = 0xffffffff;
    for (const byte of buffer) {
        crc ^= byte;
        for (let bit = 0; bit < 8; bit++) crc = (crc >>> 1) ^ (0xedb88320 & -(crc & 1));
    }
    return (crc ^ 0xffffffff) >>> 0;
}

function chunk(type, data) {
    const name = Buffer.from(type);
    const length = Buffer.alloc(4);
    const checksum = Buffer.alloc(4);
    length.writeUInt32BE(data.length);
    checksum.writeUInt32BE(crc32(Buffer.concat([name, data])));
    return Buffer.concat([length, name, data, checksum]);
}

function decodePng(png) {
    let offset = 8;
    let width;
    let height;
    const compressed = [];
    while (offset < png.length) {
        const length = png.readUInt32BE(offset);
        const type = png.toString('ascii', offset + 4, offset + 8);
        const data = png.subarray(offset + 8, offset + 8 + length);
        if (type === 'IHDR') {
            width = data.readUInt32BE(0);
            height = data.readUInt32BE(4);
            if (data[8] !== 8 || data[9] !== 6 || data[12] !== 0) throw new Error('El logo debe ser PNG RGBA de 8 bits sin entrelazar.');
        }
        if (type === 'IDAT') compressed.push(data);
        offset += length + 12;
    }

    const raw = inflateSync(Buffer.concat(compressed));
    const stride = width * 4;
    const pixels = Buffer.alloc(stride * height);
    for (let y = 0; y < height; y++) {
        const filter = raw[y * (stride + 1)];
        for (let x = 0; x < stride; x++) {
            const value = raw[y * (stride + 1) + 1 + x];
            const left = x >= 4 ? pixels[y * stride + x - 4] : 0;
            const above = y ? pixels[(y - 1) * stride + x] : 0;
            const upperLeft = y && x >= 4 ? pixels[(y - 1) * stride + x - 4] : 0;
            const predictor = filter === 0 ? 0 : filter === 1 ? left : filter === 2 ? above
                : filter === 3 ? Math.floor((left + above) / 2) : paeth(left, above, upperLeft);
            pixels[y * stride + x] = (value + predictor) & 255;
        }
    }
    return { width, height, pixels };
}

function paeth(left, above, upperLeft) {
    const estimate = left + above - upperLeft;
    const leftDistance = Math.abs(estimate - left);
    const aboveDistance = Math.abs(estimate - above);
    const upperLeftDistance = Math.abs(estimate - upperLeft);
    return leftDistance <= aboveDistance && leftDistance <= upperLeftDistance ? left : aboveDistance <= upperLeftDistance ? above : upperLeft;
}

function createPng(width, height) {
    const header = Buffer.alloc(13);
    header.writeUInt32BE(width, 0);
    header.writeUInt32BE(height, 4);
    header[8] = 8;
    header[9] = 2;

    const rows = Array.from({ length: height }, () => {
        const row = Buffer.alloc(1 + width * 3);
        for (let offset = 1; offset < row.length; offset += 3) {
            row[offset] = pixel[0]; row[offset + 1] = pixel[1]; row[offset + 2] = pixel[2];
        }
        return row;
    });
    const logoSize = Math.round(width * .43);
    const logoTop = Math.round((height - logoSize) / 2 - height * .025);
    drawLogo(rows, width, logoSize, logoTop);
    drawProgress(rows, width, Math.round(width * .2), logoTop + logoSize - Math.round(logoSize * .08));

    return Buffer.concat([
        Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]), chunk('IHDR', header),
        chunk('IDAT', deflateSync(Buffer.concat(rows))), chunk('IEND', Buffer.alloc(0)),
    ]);
}

function drawLogo(rows, canvasWidth, size, top) {
    const left = Math.round((canvasWidth - size) / 2);
    for (let y = 0; y < size; y++) {
        for (let x = 0; x < size; x++) {
            const source = ((Math.floor(y * logo.height / size) * logo.width) + Math.floor(x * logo.width / size)) * 4;
            const alpha = logo.pixels[source + 3] / 255;
            if (!alpha) continue;
            const target = 1 + (left + x) * 3;
            for (let channel = 0; channel < 3; channel++) rows[top + y][target + channel] = Math.round(logo.pixels[source + channel] * alpha + pixel[channel] * (1 - alpha));
        }
    }
}

function drawProgress(rows, canvasWidth, width, top) {
    const left = Math.round((canvasWidth - width) / 2);
    const height = Math.max(2, Math.round(canvasWidth / 390));
    for (let y = top; y < top + height; y++) for (let x = left; x < left + width; x++) {
        const active = x < left + width * .38;
        const color = active ? [95, 117, 104] : [224, 222, 213];
        const target = 1 + x * 3;
        rows[y][target] = color[0]; rows[y][target + 1] = color[1]; rows[y][target + 2] = color[2];
    }
}

mkdirSync(outputDirectory, { recursive: true });
for (const [width, height] of sizes) {
    writeFileSync(new URL(`launch-${width}x${height}.png`, outputDirectory), createPng(width, height));
}
