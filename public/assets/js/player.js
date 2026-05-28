(function () {
  'use strict';

  const dataEl = document.getElementById('episode-data');
  const videoEl = document.getElementById('player');
  const statusEl = document.getElementById('player-status');
  if (!dataEl || !videoEl) return;

  let payload = null;
  let player = null;
  let prefetched = false;
  let prefetchedHtml = null; // Stored HTML string in-memory

  // Initialize history state on initial load
  try {
    history.replaceState({ url: window.location.pathname + window.location.search }, document.title, window.location.pathname + window.location.search);
  } catch (e) {
    console.warn('[SJ Player] History API replaceState failed:', e);
  }

  // Parse initial payload
  try {
    payload = JSON.parse(dataEl.textContent || '{}');
  } catch (e) {
    setStatus('ไม่สามารถอ่านข้อมูลตอนได้: ' + e.message, true);
    return;
  }

  function setStatus(msg, error) {
    if (!statusEl) return;
    statusEl.textContent = msg || '';
    statusEl.className = 'mt-2 text-xs ' + (error ? 'text-red-400' : 'text-slate-500');
  }

  // ---------- Initialize dynamic source player ----------
  function initNewSource() {
    console.log('[SJ Player] Initializing source with payload:', payload);

    // 1. Cleanup old HLS instances & revoke blob URLs to prevent overlaps/memory leaks
    if (window.__sjHls) {
      try {
        console.log('[SJ Player] Destroying active Hls.js instance...');
        window.__sjHls.destroy();
      } catch (e) {
        console.warn('[SJ Player] Error destroying old Hls instance:', e);
      }
      window.__sjHls = null;
    }

    if (videoEl.src && videoEl.src.startsWith('blob:')) {
      try {
        console.log('[SJ Player] Revoking active Blob URL:', videoEl.src);
        URL.revokeObjectURL(videoEl.src);
      } catch (e) {
        console.warn('[SJ Player] Error revoking blob URL:', e);
      }
    }

    // Reset video element state
    videoEl.removeAttribute('src');
    videoEl.load();

    // Create a fresh abort controller for this new video stream loading session
    window.__sjAbortController = new AbortController();
    window.__sjAbortSignal = window.__sjAbortController.signal;

    // 2. Clear old subtitle tracks & inject new ones
    videoEl.innerHTML = '';
    const subtitles = payload.subtitles || [];
    subtitles.forEach((s) => {
      if (!s.vtt) return;
      const t = document.createElement('track');
      t.kind = 'subtitles';
      t.label = s.label || s.lang;
      t.srclang = (s.lang || '').split('-')[0];
      t.src = s.vtt;
      if ((s.lang || '').toLowerCase().startsWith('th')) t.default = true;
      videoEl.appendChild(t);
    });

    const sources = (payload.sources || []).filter((s) => s && s.url);
    if (sources.length === 0) {
      setStatus('ไม่พบลิงก์วิดีโอที่เล่นได้ — อาจเป็นตอนล็อกหรือแพลตฟอร์มยังไม่รองรับ', true);
      return;
    }

    // 3. Initialize Plyr if not already done
    if (!player && window.Plyr) {
      player = new window.Plyr(videoEl, {
        controls: ['play-large', 'rewind', 'play', 'fast-forward', 'progress', 'current-time', 'duration', 'mute', 'volume', 'captions', 'settings', 'pip', 'airplay', 'fullscreen'],
        settings: ['captions', 'quality', 'speed'],
        speed: { selected: 1, options: [0.5, 0.75, 1, 1.25, 1.5, 2] },
        captions: { active: true, update: true, language: 'th' },
        tooltips: { controls: true, seek: true },
        keyboard: { focused: true, global: true },
        hideControls: false,       // ← always visible like YouTube (don't auto-hide)
        clickToPlay: true,
        resetOnEnd: true,
        invertTime: false,
      });
      window.__sjPlayer = player;
    }

    // Register a new one-time canplay listener to autoplay
    videoEl.addEventListener('canplay', function () {
      console.log('[SJ Player] Dynamic source canplay event triggered. Attempting autoplay...');
      videoEl.play().catch(function (err) {
        console.log('[SJ Player] Dynamic autoplay deferred or blocked:', err.message);
      });
    }, { once: true });

    // 4. Branch: Melolo encrypted MP4 vs standard HLS
    if (payload.is_melolo && window.MeloloPlayer) {
      setStatus('กำลังเตรียมตัววิดีโอ Melolo...');
      new window.MeloloPlayer(videoEl, sources, statusEl, player).start();
      return;
    }

    const primary = sources[0];
    const isHls = /\.m3u8(?:\?|$)/i.test(primary.url);

    if (isHls) {
      if (window.Hls && window.Hls.isSupported()) {
        const hls = new window.Hls({ enableWorker: true, lowLatencyMode: false });
        window.__sjHls = hls;
        hls.loadSource(primary.url);
        hls.attachMedia(videoEl);
        hls.on(window.Hls.Events.MANIFEST_PARSED, () => {
          setStatus('พร้อมเล่น (HLS via hls.js, ' + sources.length + ' source/s)');
          if (player) {
            try {
              player.media.load();
            } catch (e) {
              console.warn('[SJ Player] Plyr media load error:', e);
            }
          }
          // Autoplay fallback inside event if canplay didn't fire yet
          videoEl.play().catch(function (err) {
            console.log('[SJ Player] HLS autoplay check:', err.message);
          });
        });
        hls.on(window.Hls.Events.ERROR, (_e, data) => {
          if (!data.fatal) return;
          setStatus('เกิดข้อผิดพลาดในการสตรีม: ' + data.type + '/' + data.details, true);
          try {
            if (data.type === window.Hls.ErrorTypes.NETWORK_ERROR) hls.startLoad();
            else if (data.type === window.Hls.ErrorTypes.MEDIA_ERROR) hls.recoverMediaError();
          } catch (_) { /* ignore */ }
        });
      } else if (videoEl.canPlayType('application/vnd.apple.mpegurl')) {
        videoEl.src = primary.url;
        setStatus('พร้อมเล่น (HLS via native — Safari/iOS)');
        videoEl.load();
        videoEl.play().catch(function (err) {
          console.log('[SJ Player] Native HLS autoplay check:', err.message);
        });
      } else {
        setStatus('เบราว์เซอร์ของคุณไม่รองรับ HLS', true);
      }
    } else {
      videoEl.src = primary.url;
      setStatus('พร้อมเล่น (' + (primary.codec || 'video') + ' / ' + (primary.mime || 'video/mp4') + ')');
      videoEl.load();
      videoEl.play().catch(function (err) {
        console.log('[SJ Player] MP4 autoplay check:', err.message);
      });
    }

    // Sync Plyr tracks after rendering
    if (player) {
      setTimeout(() => {
        try {
          player.media.load();
        } catch (e) {
          console.warn('[SJ Player] Delayed plyr load error:', e);
        }
      }, 50);
    }
  }

  // ---------- Process and swap page contents ----------
  function processNewPageHtml(html, url, isPopState) {
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');

    // Extract new payload
    const newDataEl = doc.getElementById('episode-data');
    if (!newDataEl) throw new Error('ไม่พบข้อมูลตอนในหน้าใหม่');

    let newPayload;
    try {
      newPayload = JSON.parse(newDataEl.textContent || '{}');
    } catch (e) {
      throw new Error('ไม่สามารถแยกวิเคราะห์ข้อมูลตอนใหม่ได้: ' + e.message);
    }

    // Update global payload
    payload = newPayload;

    // Update history and title
    if (!isPopState && url) {
      try {
        history.pushState({ url: url }, doc.title, url);
      } catch (e) {
        console.warn('[SJ Player] History pushState failed:', e);
      }
    }
    document.title = doc.title;

    // Swap DOM contents
    const newWatchInfo = doc.getElementById('watch-info-container');
    const currentWatchInfo = document.getElementById('watch-info-container');
    if (newWatchInfo && currentWatchInfo) {
      currentWatchInfo.innerHTML = newWatchInfo.innerHTML;
    }

    const newSidebar = doc.getElementById('episodes-sidebar');
    const currentSidebar = document.getElementById('episodes-sidebar');
    if (newSidebar && currentSidebar) {
      currentSidebar.innerHTML = newSidebar.innerHTML;
    }

    // Update video poster if applicable
    if (payload.poster) {
      videoEl.setAttribute('poster', payload.poster);
    } else {
      videoEl.removeAttribute('poster');
    }

    // Re-initialize player with new source
    initNewSource();
  }

  // ---------- Dynamic In-Place Page Navigation (SPA) ----------
  function navigateToEpisode(url, isPopState) {
    console.log('[SJ Player] SPA Navigating to:', url);

    prefetched = false;

    // 1. CRITICAL OPTIMIZATION: Abort all active fetch requests and stream decryptions IMMEDIATELY.
    // This cancels the old episode's heavy video file downloads, instantly releasing 100% network bandwidth,
    // and terminates pending key/HTML requests. If the user clicks multiple links rapidly, 
    // it cancels previous clicks instantly so the website acts on the user's latest click immediately!
    if (window.__sjAbortController) {
      try {
        console.log('[SJ Player] User action triggered. Aborting previous downloads and tasks instantly...');
        window.__sjAbortController.abort();
      } catch (e) {
        console.warn('[SJ Player] Error aborting controller on navigation:', e);
      }
    }

    // Create a new controller specifically for the new HTML page load request
    window.__sjAbortController = new AbortController();
    window.__sjAbortSignal = window.__sjAbortController.signal;

    // Check if we have pre-fetched HTML for this specific URL
    if (prefetchedHtml && (url === payload.next_episode_url || url.endsWith(payload.next_episode_url))) {
      console.log('[SJ Player] Using in-memory pre-fetched HTML.');
      try {
        const storedHtml = prefetchedHtml;
        prefetchedHtml = null; // clear it immediately
        processNewPageHtml(storedHtml, url, isPopState);
        return;
      } catch (err) {
        console.warn('[SJ Player] Error processing stored HTML, falling back to fetch:', err);
      }
    }

    // Otherwise, perform regular fetch (bound to the new AbortSignal)
    setStatus('กำลังโหลดข้อมูลตอนถัดไป...');

    fetch(url, { signal: window.__sjAbortSignal })
      .then((res) => {
        if (!res.ok) throw new Error('HTTP status ' + res.status);
        return res.text();
      })
      .then((html) => {
        processNewPageHtml(html, url, isPopState);
      })
      .catch((err) => {
        if (err.name === 'AbortError') {
          console.log('[SJ Player] Navigation load aborted due to user clicking another option.');
          return;
        }
        console.error('[SJ Player] Navigation error:', err);
        setStatus('เกิดข้อผิดพลาดในการนำทาง: ' + err.message, true);
        
        // Dynamic fallback to standard reload if network or execution fails
        setTimeout(() => {
          window.location.href = url;
        }, 1200);
      });
  }

  // ---------- Autoplay next episode + prefetch (countdown popup disabled) ----------
  videoEl.addEventListener('timeupdate', function () {
    if (!payload || !payload.next_episode_url) return;
    const duration = videoEl.duration;
    const currentTime = videoEl.currentTime;

    if (!duration || isNaN(duration)) return;

    // 1. Prefetch next episode page HTML silently using background fetch 40 seconds before the end
    if (duration - currentTime <= 40 && !prefetched) {
      prefetched = true;
      console.log('[SJ Player] Pre-fetching next episode page text silently:', payload.next_episode_url);
      fetch(payload.next_episode_url, { signal: window.__sjAbortSignal || null })
        .then((res) => {
          if (res.ok) return res.text();
        })
        .then((text) => {
          if (text) {
            prefetchedHtml = text;
            console.log('[SJ Player] Next episode HTML successfully pre-cached in memory.');
          }
        })
        .catch((err) => {
          if (err.name === 'AbortError') return;
          console.warn('[SJ Player] Silent pre-fetch failed:', err);
          prefetched = false; // Allow retry if failed
        });
    }
  });

  // Auto-advance to the next episode when the current one finishes. Uses in-place SPA
  // navigation (no full page reload) so the player keeps fullscreen and playback stays seamless.
  videoEl.addEventListener('ended', function () {
    if (payload && payload.next_episode_url) {
      console.log('[SJ Player] Video ended. Auto-advancing to next episode...');
      navigateToEpisode(payload.next_episode_url);
    }
  });

  // ---------- Global Click Interceptor for watch URLs ----------
  if (window.jQuery || window.$) {
    const $ = window.jQuery || window.$;
    $(document).on('click', 'a[href*="/watch/"]', function (e) {
      const href = $(this).attr('href');
      if (!href || href === '#' || href.startsWith('javascript:')) return;
      if ($(this).attr('onclick') && $(this).attr('onclick').includes('Swal.fire')) {
        return;
      }
      e.preventDefault();
      navigateToEpisode(href);
    });
  } else {
    document.addEventListener('click', function (e) {
      const a = e.target.closest('a[href*="/watch/"]');
      if (!a) return;
      const href = a.getAttribute('href');
      if (!href || href === '#' || href.startsWith('javascript:')) return;
      if (a.getAttribute('onclick') && a.getAttribute('onclick').includes('Swal.fire')) {
        return;
      }
      e.preventDefault();
      navigateToEpisode(href);
    });
  }

  // ---------- Popstate event listener for browser back/forward buttons ----------
  window.addEventListener('popstate', function (e) {
    if (e.state && e.state.url) {
      console.log('[SJ Player] Popstate triggered navigation to:', e.state.url);
      navigateToEpisode(e.state.url, true);
    } else {
      window.location.reload();
    }
  });

  // ---------- Window unload event listener to immediately release network ----------
  window.addEventListener('beforeunload', function () {
    if (window.__sjAbortController) {
      console.log('[SJ Player] Window unloading. Aborting all active streams...');
      window.__sjAbortController.abort();
    }
  });

  // ---------- First source initialization ----------
  initNewSource();
})();
