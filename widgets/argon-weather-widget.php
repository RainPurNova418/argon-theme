<?php
class Argon_Weather_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'argon_weather_widget',
            __('Argon 天气', 'argon'),
            array(
                'description' => __('基于 IP 地址自动显示用户当地天气与时间', 'argon'),
            )
        );
    }

    // ==== 后台设置表单 ====
    public function form($instance) {
        $title = ! empty($instance['title']) ? $instance['title'] : '';
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>">
                <?php _e('标题:', 'argon'); ?>
            </label>
            <input class="widefat"
                   id="<?php echo $this->get_field_id('title'); ?>"
                   name="<?php echo $this->get_field_name('title'); ?>"
                   type="text"
                   value="<?php echo esc_attr($title); ?>" />
        </p>
        <?php
    }

    // ==== 保存设置 ====
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (! empty($new_instance['title']))
            ? sanitize_text_field($new_instance['title'])
            : '';
        return $instance;
    }

    // ==== 前端输出 ====
    public function widget($args, $instance) {
        // 为每个小工具生成唯一 ID，防止多个实例冲突
        $uniqid = 'fw_' . uniqid();

        echo $args['before_widget'];

        // 标题
        if (! empty($instance['title'])) {
            echo $args['before_title']
                 . apply_filters('widget_title', $instance['title'])
                 . $args['after_title'];
        }

        // 全局只加载一次 Iconify（通过静态标志）
        static $iconify_loaded = false;
        if (! $iconify_loaded) {
            echo '<script src="https://code.iconify.design/3/3.1.1/iconify.min.js"></script>';
            $iconify_loaded = true;
        }
        ?>
        <!-- 天气小工具结构 -->
        <div class="fluent-weather-widget" id="<?php echo esc_attr($uniqid); ?>">
            <div class="weather-loading" id="<?php echo esc_attr($uniqid); ?>_loading">
                <div class="loader"></div>
            </div>
            <div class="weather-content" id="<?php echo esc_attr($uniqid); ?>_content">
                <div class="current-datetime" id="<?php echo esc_attr($uniqid); ?>_datetime"></div>
                <div class="weather-main">
                    <span class="iconify weather-fluent-icon" id="<?php echo esc_attr($uniqid); ?>_icon"
                          data-icon="fluent:weather-partly-cloudy-day-48-filled"></span>
                    <div class="weather-text">
                        <div class="weather-city" id="<?php echo esc_attr($uniqid); ?>_city">--</div>
                        <div class="weather-details">
                            <span class="detail-item">
                                <span class="detail-icon iconify" id="<?php echo esc_attr($uniqid); ?>_temp_icon"
                                      data-icon="fluent:temperature-20-filled"></span>
                                <span id="<?php echo esc_attr($uniqid); ?>_temp">-</span>
                            </span>
                            <span class="detail-item">
                                <span class="detail-icon iconify" id="<?php echo esc_attr($uniqid); ?>_wind_icon"
                                      data-icon="fluent:weather-squalls-20-regular"></span>
                                <span id="<?php echo esc_attr($uniqid); ?>_wind">-</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .fluent-weather-widget {
                position: relative; min-height: 110px;
                color: inherit; text-align: center;
            }
            .weather-loading { display: flex; justify-content: center; align-items: center; padding: 28px 0; }
            .loader {
                width: 28px; height: 28px; border: 3px solid rgba(128, 128, 128, 0.2);
                border-top-color: #0078d4; border-radius: 50%;
                animation: fluent-spin 0.8s linear infinite;
            }
            @keyframes fluent-spin { to { transform: rotate(360deg); } }
            .weather-content {
                opacity: 0; visibility: hidden;
                transition: opacity 0.5s ease, visibility 0.5s ease;
                padding: 12px 8px 6px 8px;
            }
            .current-datetime { font-size: 1.1em; font-weight: 500; margin-bottom: 12px; letter-spacing: 0.3px; opacity: 0.9; }
            .weather-main { display: flex; align-items: center; justify-content: center; gap: 12px; }
            .weather-fluent-icon { font-size: 48px; flex-shrink: 0; color: inherit; }
            .weather-text { text-align: left; line-height: 1.4; }
            .weather-city { font-size: 1em; font-weight: 600; margin-bottom: 6px; }
            .weather-details { display: flex; gap: 14px; font-size: 0.85em; opacity: 0.85; }
            .detail-item { display: flex; align-items: center; gap: 4px; white-space: nowrap; }
            .detail-icon { font-size: 1.2em; vertical-align: middle; }
            @media (max-width: 300px) {
                .weather-main { flex-direction: column; gap: 6px; }
                .weather-text { text-align: center; }
            }
        </style>

        <script>
            (function() {
                var U = <?php echo json_encode($uniqid); ?>;
                function $(id) { return document.getElementById(U + id); }

                // 安全过滤：只允许字母、数字、中文、空格、常见天气符号
                function sanitizeWeatherText(str) {
                    return str.replace(/[^a-zA-Z0-9\u4e00-\u9fa5\s°℃\-.]/g, '');
                }

                function updateDateTime() {
                    var now = new Date();
                    var ds = now.toLocaleDateString('zh-CN', {year:'numeric',month:'2-digit',day:'2-digit'}).replace(/\//g, '-');
                    var ts = now.toLocaleTimeString('zh-CN', {hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false});
                    var el = $('_datetime');
                    if (el) el.textContent = ds + ' ' + ts;
                }

                function mapEmojiToFluent(emoji) {
                    if (!emoji) return 'fluent:weather-partly-cloudy-day-48-filled';
                    var e = emoji.trim();
                    if (e.includes('☀️')||e.includes('☀')||e.includes('🌞')) return 'fluent:weather-sunny-48-filled';
                    if (e.includes('🌤')) return 'fluent:weather-partly-cloudy-day-48-filled';
                    if (e.includes('⛅')||e.includes('🌥')) return 'fluent:weather-partly-cloudy-day-48-filled';
                    if (e.includes('☁️')||e.includes('☁')) return 'fluent:weather-cloudy-48-filled';
                    if (e.includes('🌦')) return 'fluent:weather-drizzle-48-filled';
                    if (e.includes('🌧')||e.includes('🌧️')) return 'fluent:weather-rain-48-filled';
                    if (e.includes('❄️')||e.includes('❄')||e.includes('🌨')) return 'fluent:weather-snow-48-filled';
                    if (e.includes('🌬️')||e.includes('🌬')) return 'fluent:weather-blowing-snow-48-filled';
                    if (e.includes('🌩')||e.includes('⛈')) return 'fluent:weather-thunderstorm-48-filled';
                    if (e.includes('🌫')) return 'fluent:weather-fog-48-filled';
                    if (e.includes('🌫️')) return 'fluent:weather-haze-48-filled';
                    if (e.includes('🌪')) return 'fluent:weather-squalls-48-filled';
                    if (e.includes('🌪️')) return 'fluent:weather-duststorm-48-filled';
                    return 'fluent:weather-partly-cloudy-day-48-filled';
                }

                function applyIconFallback(elId, fallbackEmoji) {
                    var el = $(elId);
                    if (!el) return;
                    var observer = new MutationObserver(function() {
                        if (el.textContent.trim() !== '' && el.querySelector('svg') === null) {
                            el.textContent = fallbackEmoji;
                            observer.disconnect();
                        }
                    });
                    observer.observe(el, { childList: true, subtree: true });
                    setTimeout(function() {
                        if (el.querySelector('svg') === null) {
                            el.textContent = fallbackEmoji;
                            observer.disconnect();
                        }
                    }, 3000);
                }

                async function getWeather() {
                    var loading = $('_loading');
                    var content = $('_content');
                    try {
                        var ipR = await fetch('https://ipapi.co/json/');
                        if (!ipR.ok) throw new Error('IP location failed');
                        var loc = await ipR.json();
                        var city = (loc.city || 'Beijing').trim();
                        city = sanitizeWeatherText(city);   // 过滤

                        var wR = await fetch('https://wttr.in/' + encodeURIComponent(city) + '?format=%c+%t+%w&lang=zh');
                        if (!wR.ok) throw new Error('Weather fetch failed');
                        var rawHtml = await wR.text();

                        var weatherText = '';
                        try {
                            var parser = new DOMParser();
                            var doc = parser.parseFromString(rawHtml, 'text/html');
                            var term = doc.querySelector('.term-container');
                            weatherText = term ? term.textContent.trim() : rawHtml.trim();
                        } catch(e) {
                            weatherText = rawHtml.trim();
                        }

                        var firstLine = weatherText.split('\n')[0].trim();
                        var parts = firstLine.split(/\s+/);
                        var emoji = parts[0] || '';
                        var temp = parts[1] || 'N/A';
                        var wind = parts[2] || 'N/A';

                        emoji = sanitizeWeatherText(emoji);
                        temp = sanitizeWeatherText(temp);
                        wind = sanitizeWeatherText(wind);

                        $('_city').textContent = city;
                        $('_temp').textContent = temp;
                        $('_wind').textContent = wind;
                        var iconEl = $('_icon');
                        if (iconEl) iconEl.setAttribute('data-icon', mapEmojiToFluent(emoji));

                        applyIconFallback('_temp_icon', '🌡️');
                        applyIconFallback('_wind_icon', '🎐');
                    } catch(e) {
                        console.error('天气小工具出错:', e);
                        var cityEl = $('_city');
                        if (cityEl) cityEl.textContent = '火星';
                        var tempEl = $('_temp');
                        if (tempEl) tempEl.textContent = '--';
                        var windEl = $('_wind');
                        if (windEl) windEl.textContent = '--';
                    } finally {
                        if (loading) loading.style.display = 'none';
                        if (content) {
                            content.style.opacity = '1';
                            content.style.visibility = 'visible';
                        }
                    }
                }

                updateDateTime();
                setInterval(updateDateTime, 1000);
                getWeather();
            })();
        </script>
        <?php

        echo $args['after_widget'];
    }
}
