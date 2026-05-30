/**
 * MeloloPlayer — plays Melolo's CENC-encrypted MP4 files (per-sample AES-128-CTR).
 *
 * The encryption is ISO/IEC 23001-7 "cenc" (Common Encryption) as used by MPEG-DASH:
 *   - MP4 is structured with boxes (ftyp, moov, mdat, ...).
 *   - moov/trak/mdia/minf/stbl holds the sample table for each track (video + audio).
 *   - stsz = sample sizes, stsc/stco/co64 = chunk → sample mapping, senc = per-sample IVs.
 *   - Each sample is encrypted with AES-128-CTR; one key per kid, unique 8-byte IV per sample
 *     (padded to 16 bytes with zeros for the counter).
 *   - After decryption, DRM-marker boxes (encv/sinf/etc.) must be renamed to non-DRM tags
 *     or the browser refuses to play the file.
 *
 * The parser + decryptor are ported from the reference Melolo player. WebCrypto AES-CTR is used
 * for hardware acceleration; we don't ship a software fallback because the project requires
 * modern browsers anyway.
 */
(function (global) {
  'use strict';

  function readU32(buf, off) {
    return (buf[off] << 24 | buf[off + 1] << 16 | buf[off + 2] << 8 | buf[off + 3]) >>> 0;
  }
  function readU64(buf, off) {
    return readU32(buf, off) * 0x100000000 + readU32(buf, off + 4);
  }
  function readTag(buf, off) {
    return String.fromCharCode(buf[off], buf[off + 1], buf[off + 2], buf[off + 3]);
  }
  function writeTag(buf, off, tag) {
    for (let i = 0; i < 4; i++) buf[off + i] = tag.charCodeAt(i);
  }
  function iterBoxes(buf, start, end) {
    const boxes = [];
    let off = start;
    while (off + 8 <= end) {
      const size = readU32(buf, off);
      if (size < 8 || off + size > end) break;
      boxes.push({ offset: off, size, tag: readTag(buf, off + 4), bodyOff: off + 8 });
      off += size;
    }
    return boxes;
  }
  function findBox(buf, start, end, tag) {
    for (const b of iterBoxes(buf, start, end)) if (b.tag === tag) return b;
    return null;
  }
  function walkPath(buf, start, end, ...tags) {
    let s = start, e = end, found = null;
    for (const tag of tags) {
      found = null;
      for (const b of iterBoxes(buf, s, e)) {
        if (b.tag === tag) { found = b; s = b.bodyOff; e = b.offset + b.size; break; }
      }
      if (!found) return null;
    }
    return found;
  }

  function parseSampleTable(buf, stblStart, stblEnd) {
    const find = (tag) => findBox(buf, stblStart, stblEnd, tag);
    const stsz = find('stsz'), stsc = find('stsc'), stco = find('stco'), co64 = find('co64'), senc = find('senc');
    if (!stsz || !stsc || (!stco && !co64)) return null;

    const defaultSz = readU32(buf, stsz.bodyOff + 4);
    const n = readU32(buf, stsz.bodyOff + 8);
    const sizes = new Array(n);
    if (defaultSz === 0) {
      for (let i = 0; i < n; i++) sizes[i] = readU32(buf, stsz.bodyOff + 12 + i * 4);
    } else { sizes.fill(defaultSz); }

    let chunkOffs;
    if (stco) {
      const cnt = readU32(buf, stco.bodyOff + 4);
      chunkOffs = new Array(cnt);
      for (let i = 0; i < cnt; i++) chunkOffs[i] = readU32(buf, stco.bodyOff + 8 + i * 4);
    } else {
      const cnt = readU32(buf, co64.bodyOff + 4);
      chunkOffs = new Array(cnt);
      for (let i = 0; i < cnt; i++) chunkOffs[i] = readU64(buf, co64.bodyOff + 8 + i * 8);
    }

    const ne = readU32(buf, stsc.bodyOff + 4);
    const entries = [];
    for (let i = 0; i < ne; i++) {
      entries.push({
        firstChunk: readU32(buf, stsc.bodyOff + 8 + i * 12),
        spc:        readU32(buf, stsc.bodyOff + 12 + i * 12),
      });
    }

    const sampleOffs = [];
    let si = 0;
    for (let ci = 0; ci < chunkOffs.length && si < n; ci++) {
      let spc = 1;
      for (const e of entries) { if (ci + 1 >= e.firstChunk) spc = e.spc; else break; }
      let cur = chunkOffs[ci];
      for (let k = 0; k < spc && si < n; k++) { sampleOffs.push(cur); cur += sizes[si]; si++; }
    }

    // Per-sample IVs from `senc` box (CENC). Each IV is 8 bytes; sub-sample patterns
    // (when flags & 0x02) add a 2-byte count + 6 bytes per pattern that we skip.
    const ivs = [];
    if (senc) {
      const flags = (buf[senc.bodyOff + 1] << 16) | (buf[senc.bodyOff + 2] << 8) | buf[senc.bodyOff + 3];
      const cnt = readU32(buf, senc.bodyOff + 4);
      let off = senc.bodyOff + 8;
      for (let i = 0; i < cnt; i++) {
        ivs.push(new Uint8Array(buf.buffer, buf.byteOffset + off, 8));
        off += 8;
        if (flags & 0x02) {
          const subCount = (buf[off] << 8) | buf[off + 1];
          off += 2 + subCount * 6;
        }
      }
    }
    return { sampleOffs, sizes, ivs };
  }

  function parseAllTracks(buf, moov) {
    const tracks = [];
    for (const trak of iterBoxes(buf, moov.bodyOff, moov.offset + moov.size)) {
      if (trak.tag !== 'trak') continue;
      const mdia = walkPath(buf, trak.bodyOff, trak.offset + trak.size, 'mdia');
      if (!mdia) continue;
      const hdlr = walkPath(buf, mdia.bodyOff, mdia.offset + mdia.size, 'hdlr');
      if (!hdlr) continue;
      const handler = readTag(buf, hdlr.offset + 16);
      if (handler !== 'vide' && handler !== 'soun') continue;
      const stbl = walkPath(buf, mdia.bodyOff, mdia.offset + mdia.size, 'minf', 'stbl');
      if (!stbl) continue;
      const t = parseSampleTable(buf, stbl.bodyOff, stbl.offset + stbl.size);
      if (t && t.ivs.length > 0) tracks.push(t);
    }
    return tracks;
  }

  /** Decrypt a contiguous range of samples using WebCrypto AES-CTR (hardware-accelerated). */
  async function decryptBatch(buf, track, cryptoKey, from, to) {
    const promises = new Array(to - from);
    for (let i = from; i < to; i++) {
      const counter = new Uint8Array(16);
      counter.set(track.ivs[i], 0);     // 8-byte IV at offset 0; counter[8..15] = 0
      const off = track.sampleOffs[i];
      const sz = track.sizes[i];
      promises[i - from] = crypto.subtle.decrypt(
        { name: 'AES-CTR', counter, length: 64 },
        cryptoKey,
        buf.buffer.slice(buf.byteOffset + off, buf.byteOffset + off + sz),
      ).then((dec) => { buf.set(new Uint8Array(dec), off); });
    }
    return Promise.all(promises);
  }

  /**
   * Neutralize DRM markers so a vanilla <video> element accepts the file:
   *   - encv  → hvc1   (encrypted video sample entry → plain HEVC)
   *   - enca  → mp4a   (encrypted audio sample entry → plain AAC)
   *   - sinf/schm/schi/senc/saio/saiz/tenc/frma/pssh → free  (DRM metadata wiped)
   */
  function neutralizeDRM(buf, moovStart, moovEnd) {
    const rename = {
      encv: 'hvc1', enca: 'mp4a',
      sinf: 'free', schm: 'free', schi: 'free', senc: 'free',
      saio: 'free', saiz: 'free', tenc: 'free', frma: 'free', pssh: 'free',
    };
    for (let i = moovStart + 4; i + 4 <= moovEnd; i++) {
      const tag = readTag(buf, i);
      if (tag in rename) {
        const sz = readU32(buf, i - 4);
        if (sz >= 8 && sz <= moovEnd - i + 4) writeTag(buf, i, rename[tag]);
      }
    }
    // top-level pssh boxes (Protection System Specific Header) — outside moov
    let off = 0;
    while (off + 8 <= buf.length) {
      const sz = readU32(buf, off);
      if (sz < 8 || off + sz > buf.length) break;
      const tag = readTag(buf, off + 4);
      if (tag === 'pssh') writeTag(buf, off + 4, 'free');
      off += sz;
    }
  }

  /** Fetch URL → decrypt CENC → return Blob ready for <video>. */
  async function decryptCENC(cdnURL, keyHex, onProgress) {
    const log = onProgress || (() => {});
    const t0 = Date.now();
    const keyBytes = new Uint8Array(keyHex.match(/.{2}/g).map((h) => parseInt(h, 16)));
    const cryptoKey = await crypto.subtle.importKey('raw', keyBytes, { name: 'AES-CTR' }, false, ['decrypt']);

    log('กำลังเชื่อมต่อ CDN...');
    const fetchOptions = { credentials: 'same-origin' };
    if (window.__sjAbortSignal) {
      fetchOptions.signal = window.__sjAbortSignal;
    }
    const resp = await fetch(cdnURL, fetchOptions);
    if (!resp.ok) throw new Error(`CDN HTTP ${resp.status}`);
    const total = parseInt(resp.headers.get('Content-Length') || '0');
    const reader = resp.body.getReader();

    // Wire up immediate cancel hook on abort signal to close the network stream socket instantly
    const abortHandler = () => {
      console.log('[Melolo] Abort signal fired. Cancelling active stream reader instantly...');
      try {
        reader.cancel();
      } catch (e) { /* ignore */ }
    };
    if (window.__sjAbortSignal) {
      window.__sjAbortSignal.addEventListener('abort', abortHandler);
    }

    try {
      let buf = total > 0 ? new Uint8Array(total) : null;
      const fallbackChunks = total > 0 ? null : [];
      let received = 0;

      // Decrypt as data streams in — start as soon as moov box is parsed
      let moovParsed = false;
      let moovBox = null;
      let tracks = null;
      const decUp = [];
      const inFlight = [];
      let lastOverlap = 0;
      const OVERLAP_BATCH = 50;

      while (true) {
        // Double check abort state inside the loop
        if (window.__sjAbortSignal && window.__sjAbortSignal.aborted) {
          throw new DOMException('Stream aborted by user request', 'AbortError');
        }

        const { done, value } = await reader.read();
        if (done) break;
        if (buf) { buf.set(value, received); } else { fallbackChunks.push(value); }
        received += value.length;

        const pct = total > 0 ? Math.round(received / total * 100) : 0;
        const mb = (received / 1048576).toFixed(1);

        if (!moovParsed && buf && received > 512) {
          const moov = walkPath(buf, 0, received, 'moov');
          if (moov && moov.offset + moov.size <= received) {
            moovParsed = true;
            moovBox = moov;
            tracks = parseAllTracks(buf, moov);
            tracks.forEach(() => decUp.push(0));
          }
        }

        if (moovParsed && tracks && Date.now() - lastOverlap > 30) {
          for (let ti = 0; ti < tracks.length; ti++) {
            const t = tracks[ti];
            const cnt = Math.min(t.sampleOffs.length, t.ivs.length);
            let avail = decUp[ti];
            while (avail < cnt && t.sampleOffs[avail] + t.sizes[avail] <= received) avail++;
            if (avail - decUp[ti] >= OVERLAP_BATCH) {
              inFlight.push(decryptBatch(buf, t, cryptoKey, decUp[ti], avail));
              decUp[ti] = avail;
              lastOverlap = Date.now();
            }
          }
          log(`ดาวน์โหลด ${pct}% (${mb}MB) + กำลังถอดรหัส...`);
        } else {
          log(`ดาวน์โหลด ${pct}% (${mb}MB)`);
        }
      }

      if (!buf) {
        buf = new Uint8Array(received);
        let pos = 0;
        for (const c of fallbackChunks) { buf.set(c, pos); pos += c.length; }
      }
      log(`ดาวน์โหลดเสร็จ ${(received / 1048576).toFixed(1)} MB`);

      if (!moovParsed) {
        log('กำลังแยก MP4 box...');
        const moov = walkPath(buf, 0, buf.length, 'moov');
        if (!moov) throw new Error('No moov box found — file may not be MP4');
        moovBox = moov;
        tracks = parseAllTracks(buf, moov);
        tracks.forEach(() => decUp.push(0));
      }

      if (!tracks || tracks.length === 0) {
        // No encrypted tracks → file is plain, just return as Blob
        log('ไฟล์เป็น plain mp4 ไม่ต้องถอดรหัส');
        return new Blob([buf], { type: 'video/mp4' });
      }

      // Finish decrypting any remaining samples
      let totalSamples = 0;
      let doneSamples = 0;
      for (let ti = 0; ti < tracks.length; ti++) {
        const t = tracks[ti];
        const cnt = Math.min(t.sampleOffs.length, t.ivs.length);
        totalSamples += cnt;
        doneSamples += decUp[ti];
        const BATCH = 500;
        for (let start = decUp[ti]; start < cnt; start += BATCH) {
          const end = Math.min(start + BATCH, cnt);
          inFlight.push(decryptBatch(buf, t, cryptoKey, start, end));
        }
      }

      log(`กำลังถอดรหัส (${doneSamples}/${totalSamples} ตัวอย่างเสร็จระหว่างดาวน์โหลด)...`);
      if (inFlight.length > 0) await Promise.all(inFlight);

      log('กำลังลบ DRM markers...');
      if (moovBox) neutralizeDRM(buf, moovBox.offset, moovBox.offset + moovBox.size);

      const took = ((Date.now() - t0) / 1000).toFixed(1);
      log(`พร้อมเล่น ${(buf.length / 1048576).toFixed(1)}MB / ${took}s`);
      return new Blob([buf], { type: 'video/mp4' });
    } finally {
      // Always detach the abort listener so we don't leak handlers across episodes.
      if (window.__sjAbortSignal) {
        window.__sjAbortSignal.removeEventListener('abort', abortHandler);
      }
    }
  }

  class MeloloPlayer {
    constructor(videoEl, sources, statusEl, plyr) {
      this.video = videoEl;
      this.sources = sources;
      this.statusEl = statusEl;
      this.plyr = plyr || null;
    }

    setStatus(msg, error) {
      if (!this.statusEl) return;
      this.statusEl.textContent = msg || '';
      this.statusEl.className = 'mt-2 text-xs ' + (error ? 'text-red-400' : 'text-slate-500');
    }

    /** Swap source — direct <video>.src is more reliable than Plyr's source setter
     *  when starting from a Plyr instance that was init'd without a source. */
    setSource(url, mime) {
      if (this.video.src && this.video.src.startsWith('blob:')) {
        URL.revokeObjectURL(this.video.src);
      }
      this.video.src = url;
      this.video.load();
      // Wire up an error reporter so codec-unsupported / fetch failures surface clearly
      this.video.addEventListener('error', () => {
        const err = this.video.error;
        const codes = { 1: 'aborted', 2: 'network', 3: 'decode', 4: 'src-not-supported' };
        const codec = err ? (codes[err.code] || ('code-' + err.code)) : 'unknown';
        this.setStatus('เบราว์เซอร์เล่นไม่ได้: ' + codec
          + ' (อาจไม่รองรับ codec — Melolo มักเป็น HEVC/H.265 ต้องใช้ Safari หรือ Chrome เวอร์ชันใหม่บนเครื่อง Apple/Windows ที่มี HW decoder)', true);
      }, { once: true });
      this.video.addEventListener('canplay', () => {
        this.setStatus('พร้อมเล่น — กดปุ่ม play');
      }, { once: true });
      this.video.play().catch(() => { /* autoplay blocked — user clicks play */ });
    }

    async start() {
      const src = this.sources[0];
      if (!src || !src.url) {
        this.setStatus('Melolo: ไม่พบลิงก์วิดีโอ', true);
        return;
      }
      if (!src.kid) {
        // No kid means file is plain — just play directly
        console.log('[Melolo] no kid, playing as plain mp4');
        this.setSource(src.url, 'video/mp4');
        this.setStatus('พร้อมเล่น (plain mp4)');
        return;
      }
      try {
        this.setStatus('กำลังดึงคีย์ถอดรหัส...');
        const fetchOptions = { credentials: 'same-origin' };
        if (window.__sjAbortSignal) {
          fetchOptions.signal = window.__sjAbortSignal;
        }
        const keyResp = await fetch('/api/melolo/key/' + encodeURIComponent(src.kid), fetchOptions);
        if (!keyResp.ok) throw new Error('ดึงคีย์ไม่ได้ (HTTP ' + keyResp.status + ')');
        const { key } = await keyResp.json();
        if (!key) throw new Error('ไม่มีคีย์ใน response');

        const blob = await decryptCENC(src.url, key, (msg) => this.setStatus(msg));
        const blobUrl = URL.createObjectURL(blob);
        this.setSource(blobUrl, 'video/mp4');
      } catch (e) {
        console.error('[Melolo]', e);
        this.setStatus('Melolo: ' + e.message, true);
      }
    }
  }

  global.MeloloPlayer = MeloloPlayer;
  global.MeloloDecrypt = { decrypt: decryptCENC };  // exposed for debugging
})(window);
