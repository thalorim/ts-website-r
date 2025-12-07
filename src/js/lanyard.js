/*!
 * Discord Lanyard Widget
 * Fetches and displays Discord presence data using Lanyard API
 */

(function() {
    'use strict';

    const LANYARD_API = 'https://api.lanyard.rest/v1/users/';
    const UPDATE_INTERVAL = 30000; // 30 seconds

    class LanyardWidget {
        constructor(element) {
            this.element = element;
            this.discordId = element.getAttribute('data-discord-id');
            
            if (!this.discordId) {
                this.showError('No Discord ID provided');
                return;
            }

            this.init();
        }

        async init() {
            await this.fetchData();
            // Update every 30 seconds
            setInterval(() => this.fetchData(), UPDATE_INTERVAL);
        }

        async fetchData() {
            try {
                const response = await fetch(LANYARD_API + this.discordId);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                
                if (data.success && data.data) {
                    this.render(data.data);
                } else {
                    this.showError('Failed to load Discord data');
                }
            } catch (error) {
                console.error('Lanyard fetch error:', error);
                this.showError('Unable to connect to Discord');
            }
        }

        render(data) {
            const statusMap = {
                'online': 'Online',
                'idle': 'Idle',
                'dnd': 'Do Not Disturb',
                'offline': 'Offline'
            };

            const statusText = statusMap[data.discord_status] || 'Unknown';
            const avatarUrl = data.discord_user.avatar 
                ? `https://cdn.discordapp.com/avatars/${data.discord_user.id}/${data.discord_user.avatar}.${data.discord_user.avatar.startsWith('a_') ? 'gif' : 'png'}?size=128`
                : `https://cdn.discordapp.com/embed/avatars/${parseInt(data.discord_user.discriminator) % 5}.png`;

            let html = `
                <div class="lanyard-content">
                    <div class="lanyard-user">
                        <div class="lanyard-avatar">
                            <img src="${this.escapeHtml(avatarUrl)}" alt="${this.escapeHtml(data.discord_user.username)}">
                            <div class="lanyard-status-indicator ${this.escapeHtml(data.discord_status)}"></div>
                        </div>
                        <div class="lanyard-user-info">
                            <div class="lanyard-username">${this.escapeHtml(data.discord_user.username)}</div>
                            <div class="lanyard-status-text"><strong>Status:</strong> <span class="status-value ${this.escapeHtml(data.discord_status)}">${statusText}</span></div>
                            <div class="lanyard-discord-id"><strong>ID:</strong> ${this.escapeHtml(data.discord_user.id)}</div>
                        </div>
                    </div>
            `;

            // Add activity if present
            if (data.activities && data.activities.length > 0) {
                const activity = data.activities[0];
                
                if (activity.type !== 4) { // Skip custom status (type 4)
                    const activityTypes = {
                        0: 'Playing',
                        1: 'Streaming',
                        2: 'Listening to',
                        3: 'Watching',
                        5: 'Competing in'
                    };

                    const activityType = activityTypes[activity.type] || 'Activity';
                    let activityImage = null;

                    if (activity.assets) {
                        if (activity.assets.large_image) {
                            if (activity.assets.large_image.startsWith('mp:')) {
                                // External asset
                                activityImage = activity.assets.large_image.replace('mp:', 'https://media.discordapp.net/');
                            } else {
                                // Application asset
                                activityImage = `https://cdn.discordapp.com/app-assets/${activity.application_id}/${activity.assets.large_image}.png`;
                            }
                        }
                    }

                    html += `
                        <div class="lanyard-activity">
                            <div class="lanyard-activity-header">${activityType}</div>
                            <div class="lanyard-activity-content">
                    `;

                    if (activityImage) {
                        html += `<img src="${this.escapeHtml(activityImage)}" alt="${this.escapeHtml(activity.name)}" class="lanyard-activity-image">`;
                    }

                    html += `
                                <div class="lanyard-activity-details">
                                    <div class="lanyard-activity-name">${this.escapeHtml(activity.name)}</div>
                    `;

                    if (activity.details) {
                        html += `<div class="lanyard-activity-details-text">${this.escapeHtml(activity.details)}</div>`;
                    }

                    if (activity.state) {
                        html += `<div class="lanyard-activity-state">${this.escapeHtml(activity.state)}</div>`;
                    }

                    html += `
                                </div>
                            </div>
                        </div>
                    `;
                }
            }

            // Add Spotify if listening
            if (data.listening_to_spotify && data.spotify) {
                const spotify = data.spotify;
                const albumArt = spotify.album_art_url;

                html += `
                    <div class="lanyard-activity">
                        <div class="lanyard-activity-header">Listening to Spotify</div>
                        <div class="lanyard-activity-content">
                            <img src="${this.escapeHtml(albumArt)}" alt="${this.escapeHtml(spotify.album)}" class="lanyard-activity-image">
                            <div class="lanyard-activity-details">
                                <div class="lanyard-activity-name">${this.escapeHtml(spotify.song)}</div>
                                <div class="lanyard-activity-details-text">by ${this.escapeHtml(spotify.artist)}</div>
                                <div class="lanyard-activity-state">on ${this.escapeHtml(spotify.album)}</div>
                            </div>
                        </div>
                    </div>
                `;
            }

            html += '</div>';

            this.element.innerHTML = html;
        }

        showError(message) {
            this.element.innerHTML = `<div class="lanyard-error"><i class="fas fa-exclamation-triangle"></i> ${this.escapeHtml(message)}</div>`;
        }

        escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    // Initialize all Lanyard widgets on page load
    function initLanyardWidgets() {
        const widgets = document.querySelectorAll('.lanyard-widget');
        widgets.forEach(widget => {
            if (!widget.hasAttribute('data-initialized')) {
                new LanyardWidget(widget);
                widget.setAttribute('data-initialized', 'true');
            }
        });
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLanyardWidgets);
    } else {
        initLanyardWidgets();
    }
})();
