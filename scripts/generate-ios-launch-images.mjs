import { deflateSync } from 'node:zlib';
import { mkdirSync, writeFileSync } from 'node:fs';

const sizes = [
    [640, 1136], [750, 1334], [828, 1792], [1080, 2340], [1125, 2436],
    [1170, 2532], [1179, 2556], [1206, 2622], [1242, 2688], [1284, 2778],
    [1290, 2796], [1320, 2868],
];
const outputDirectory = new URL('../public/images/launch/', import.meta.url);
const pixel = [244, 241, 232];

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

function createPng(width, height) {
    const header = Buffer.alloc(13);
    header.writeUInt32BE(width, 0);
    header.writeUInt32BE(height, 4);
    header[8] = 8;
    header[9] = 2;

    const row = Buffer.alloc(1 + width * 3);
    for (let offset = 1; offset < row.length; offset += 3) {
        row[offset] = pixel[0];
        row[offset + 1] = pixel[1];
        row[offset + 2] = pixel[2];
    }

    return Buffer.concat([
        Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]),
        chunk('IHDR', header),
        chunk('IDAT', deflateSync(Buffer.concat(Array.from({ length: height }, () => row)))),
        chunk('IEND', Buffer.alloc(0)),
    ]);
}

mkdirSync(outputDirectory, { recursive: true });
for (const [width, height] of sizes) {
    writeFileSync(new URL(`launch-${width}x${height}.png`, outputDirectory), createPng(width, height));
}
