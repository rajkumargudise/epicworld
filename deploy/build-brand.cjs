/*
 * Regenerates every brand image from the original EPIC World logo files in
 * resources/brand-src (the originals from the old WordPress site).
 *
 *   cd <any temp dir> && npm i sharp && node <repo>/deploy/build-brand.cjs <repo>
 *
 * Outputs into <repo>/public/brand and <repo>/public/favicon.ico. Not part of the
 * app's runtime - run it only when the source logos change.
 */
const path = require('path');
const fs = require('fs');
const sharp = require('sharp');

const repo = path.resolve(process.argv[2] || '.');
const src = path.join(repo, 'resources/brand-src');
const out = path.join(repo, 'public/brand');
fs.mkdirSync(out, { recursive: true });

const TRANSPARENT = { r: 0, g: 0, b: 0, alpha: 0 };

async function trimmed(file) {
    // Crop the empty padding around the artwork so the logo can be sized precisely.
    const buf = await sharp(path.join(src, file)).ensureAlpha().trim({ background: TRANSPARENT, threshold: 10 }).png().toBuffer();
    const meta = await sharp(buf).metadata();
    return { buf, w: meta.width, h: meta.height };
}

/** Logo centred on a solid square tile (white by default) with the given padding ratio. */
async function tile(logo, size, { pad = 0.12, radius = 0, bg = { r: 255, g: 255, b: 255, alpha: 1 } } = {}) {
    const inner = Math.round(size * (1 - pad * 2));
    const scaled = await sharp(logo.buf).resize({ width: inner, height: inner, fit: 'inside' }).png().toBuffer();
    let img = sharp({ create: { width: size, height: size, channels: 4, background: bg } }).composite([{ input: scaled, gravity: 'centre' }]);

    if (radius > 0) {
        const mask = Buffer.from(`<svg width="${size}" height="${size}"><rect width="${size}" height="${size}" rx="${Math.round(size * radius)}" fill="#fff"/></svg>`);
        img = sharp(await img.png().toBuffer()).composite([{ input: mask, blend: 'dest-in' }]);
    }

    return img.png().toBuffer();
}

function ico(images) {
    const header = Buffer.alloc(6);
    header.writeUInt16LE(1, 2);
    header.writeUInt16LE(images.length, 4);
    let offset = 6 + images.length * 16;
    const entries = images.map(({ size, data }) => {
        const e = Buffer.alloc(16);
        e.writeUInt8(size >= 256 ? 0 : size, 0);
        e.writeUInt8(size >= 256 ? 0 : size, 1);
        e.writeUInt16LE(1, 4);
        e.writeUInt16LE(32, 6);
        e.writeUInt32LE(data.length, 8);
        e.writeUInt32LE(offset, 12);
        offset += data.length;
        return e;
    });

    return Buffer.concat([header, ...entries, ...images.map((i) => i.data)]);
}

(async () => {
    const black = await trimmed('Epic-World-Logo-Black.png');
    const white = await trimmed('Epic-World-Logo-White.png');
    console.log('trimmed logo', black.w + 'x' + black.h, '/', white.w + 'x' + white.h);

    // Header / footer logos (light theme = black wordmark, dark theme = white wordmark).
    for (const [name, logo] of [['logo-black', black], ['logo-white', white]]) {
        const resized = sharp(logo.buf).resize({ width: 480 });
        await resized.clone().png({ compressionLevel: 9 }).toFile(path.join(out, name + '.png'));
        await resized.clone().webp({ quality: 90 }).toFile(path.join(out, name + '.webp'));
    }

    // Favicons & app icons: the original logo on a white tile so it is visible on dark tab bars.
    const f16 = await tile(black, 16, { pad: 0.04, radius: 0.18 });
    const f32 = await tile(black, 32, { pad: 0.05, radius: 0.18 });
    const f48 = await tile(black, 48, { pad: 0.06, radius: 0.18 });
    fs.writeFileSync(path.join(out, 'favicon-16x16.png'), f16);
    fs.writeFileSync(path.join(out, 'favicon-32x32.png'), f32);
    fs.writeFileSync(path.join(repo, 'public/favicon.ico'), ico([{ size: 16, data: f16 }, { size: 32, data: f32 }, { size: 48, data: f48 }]));
    fs.writeFileSync(path.join(out, 'apple-touch-icon.png'), await tile(black, 180, { pad: 0.1 }));
    fs.writeFileSync(path.join(out, 'icon-192.png'), await tile(black, 192, { pad: 0.08, radius: 0.18 }));
    fs.writeFileSync(path.join(out, 'icon-512.png'), await tile(black, 512, { pad: 0.08, radius: 0.18 }));
    fs.writeFileSync(path.join(out, 'icon-maskable-512.png'), await tile(black, 512, { pad: 0.22 }));

    // Default social-sharing image (Open Graph / Twitter), 1200x630.
    const W = 1200;
    const H = 630;
    const logoH = 360;
    const logoBuf = await sharp(white.buf).resize({ height: logoH }).png().toBuffer();
    const bg = Buffer.from(`<svg width="${W}" height="${H}"><defs>
        <radialGradient id="a" cx="18%" cy="0%" r="70%"><stop offset="0" stop-color="#8b7bff" stop-opacity=".45"/><stop offset="1" stop-color="#07070d" stop-opacity="0"/></radialGradient>
        <radialGradient id="b" cx="95%" cy="10%" r="55%"><stop offset="0" stop-color="#38bdf8" stop-opacity=".25"/><stop offset="1" stop-color="#07070d" stop-opacity="0"/></radialGradient>
        </defs><rect width="100%" height="100%" fill="#07070d"/><rect width="100%" height="100%" fill="url(#a)"/><rect width="100%" height="100%" fill="url(#b)"/></svg>`);
    await sharp(bg).composite([{ input: logoBuf, gravity: 'centre' }]).jpeg({ quality: 88, mozjpeg: true }).toFile(path.join(out, 'og-default.jpg'));

    console.log('written to', out);
})();
