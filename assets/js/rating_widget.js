/**
 * CyclePoint Rating Widget
 * Include on: chat.php, view_profile.php, item_details.php, profile.php
 *
 * Exposes:
 *   CyclePointRating.openModal(rateeId, rateeName, rateePicture)
 *   CyclePointRating.renderBadge(containerId, userId)
 *   CyclePointRating.renderReviews(containerId, userId)
 */

(function () {
    'use strict';

    // ── Hint text per star ──────────────────────────────────────
    const HINTS = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];

    // ── Inject modal HTML once ──────────────────────────────────
    function injectModal() {
        if (document.getElementById('ratingModal')) return;
        document.body.insertAdjacentHTML('beforeend', `
        <div id="ratingModal">
            <div class="rm-overlay" onclick="CyclePointRating.closeModal()"></div>
            <div class="rm-box">
                <div class="rm-header">
                    <img class="rm-avatar" id="rmAvatar" src="assets/images/profile-picture.png" alt="">
                    <div class="rm-header-text">
                        <h3 id="rmTitle">Rate User</h3>
                        <p>Share your experience with this trader</p>
                    </div>
                    <button class="rm-close" onclick="CyclePointRating.closeModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="rmFormArea">
                    <div class="rm-body">
                        <div class="rm-stars-label">Your Rating <span style="color:#e74c3c">*</span></div>
                        <div class="rm-star-picker" id="rmStarPicker">
                            <span class="s" data-val="1">★</span>
                            <span class="s" data-val="2">★</span>
                            <span class="s" data-val="3">★</span>
                            <span class="s" data-val="4">★</span>
                            <span class="s" data-val="5">★</span>
                        </div>
                        <div class="rm-rating-hint" id="rmHint">Click a star to rate</div>
                        <textarea id="rmComment" placeholder="Write a short review (optional)..." maxlength="500"></textarea>
                        <div class="rm-char-count"><span id="rmCharCount">0</span>/500</div>
                    </div>
                    <div class="rm-footer">
                        <button class="rm-btn-cancel" onclick="CyclePointRating.closeModal()">Cancel</button>
                        <button class="rm-btn-submit" id="rmSubmitBtn" onclick="CyclePointRating.submitRating()" disabled>
                            <i class="fas fa-star"></i> Submit Rating
                        </button>
                    </div>
                </div>
                <div id="rmSuccessArea" style="display:none">
                    <div class="rm-success">
                        <div class="rm-check">⭐</div>
                        <h3>Rating Submitted!</h3>
                        <p id="rmSuccessMsg">Your rating has been saved.</p>
                        <button class="rm-btn-submit" style="width:100%;max-width:200px;margin:0 auto;display:block"
                                onclick="CyclePointRating.closeModal()">Done</button>
                    </div>
                </div>
            </div>
        </div>`);

        // Star picker events
        const picker = document.getElementById('rmStarPicker');
        picker.addEventListener('mouseover', e => {
            if (!e.target.classList.contains('s')) return;
            const val = +e.target.dataset.val;
            highlightStars(val, true);
            document.getElementById('rmHint').textContent = HINTS[val];
        });
        picker.addEventListener('mouseout', () => {
            highlightStars(_state.selected, false);
            document.getElementById('rmHint').textContent = _state.selected ? HINTS[_state.selected] : 'Click a star to rate';
        });
        picker.addEventListener('click', e => {
            if (!e.target.classList.contains('s')) return;
            _state.selected = +e.target.dataset.val;
            highlightStars(_state.selected, false);
            document.getElementById('rmHint').textContent = HINTS[_state.selected];
            document.getElementById('rmSubmitBtn').disabled = false;
        });

        // Char counter
        document.getElementById('rmComment').addEventListener('input', function () {
            document.getElementById('rmCharCount').textContent = this.value.length;
        });
    }

    function highlightStars(upTo, isHover) {
        document.querySelectorAll('#rmStarPicker .s').forEach(s => {
            s.classList.remove('selected', 'hovered');
            if (+s.dataset.val <= upTo) {
                s.classList.add(isHover ? 'hovered' : 'selected');
            }
        });
    }

    // ── Internal state ──────────────────────────────────────────
    const _state = { rateeId: 0, selected: 0 };

    // ── Public API ──────────────────────────────────────────────
    window.CyclePointRating = {

        openModal(rateeId, rateeName, rateePicture, existingRating, existingComment) {
            injectModal();
            _state.rateeId  = rateeId;
            _state.selected = existingRating || 0;

            document.getElementById('rmAvatar').src    = rateePicture || 'assets/images/profile-picture.png';
            document.getElementById('rmTitle').textContent = 'Rate ' + rateeName;
            document.getElementById('rmComment').value = existingComment || '';
            document.getElementById('rmCharCount').textContent = (existingComment || '').length;
            document.getElementById('rmHint').textContent = _state.selected ? HINTS[_state.selected] : 'Click a star to rate';
            document.getElementById('rmSubmitBtn').disabled = !_state.selected;

            // Show form area, hide success
            document.getElementById('rmFormArea').style.display  = '';
            document.getElementById('rmSuccessArea').style.display = 'none';

            highlightStars(_state.selected, false);

            const modal = document.getElementById('ratingModal');
            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('show'), 10);
            document.body.style.overflow = 'hidden';
        },

        closeModal() {
            const modal = document.getElementById('ratingModal');
            if (!modal) return;
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
                document.body.style.overflow = '';
                _state.selected = 0;
            }, 300);
        },

        submitRating() {
            if (!_state.selected) return;
            const btn     = document.getElementById('rmSubmitBtn');
            const comment = document.getElementById('rmComment').value.trim();
            btn.disabled  = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            fetch('submit_rating.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    ratee_id: _state.rateeId,
                    rating:   _state.selected,
                    comment:  comment
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('rmFormArea').style.display   = 'none';
                    document.getElementById('rmSuccessArea').style.display = '';
                    document.getElementById('rmSuccessMsg').textContent =
                        `Average rating for this user is now ${data.avg_rating}★ (${data.total} review${data.total !== 1 ? 's' : ''}).`;

                    // Refresh any badge on the page for this ratee
                    CyclePointRating.renderBadge('cp-rating-badge-' + _state.rateeId, _state.rateeId);
                } else {
                    alert(data.error || 'Something went wrong.');
                    btn.disabled  = false;
                    btn.innerHTML = '<i class="fas fa-star"></i> Submit Rating';
                }
            })
            .catch(() => {
                alert('Network error. Please try again.');
                btn.disabled  = false;
                btn.innerHTML = '<i class="fas fa-star"></i> Submit Rating';
            });
        },

        /**
         * Render a star badge into an element.
         * containerId: id of the element to fill
         * userId: whose rating to show
         */
        renderBadge(containerId, userId) {
            const el = document.getElementById(containerId);
            if (!el) return;
            fetch(`get_ratings.php?user_id=${userId}`, { credentials: 'same-origin' })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;
                    if (data.total_ratings === 0) {
                        el.innerHTML = '<span style="font-size:13px;color:#9ca3af;"><i class="fas fa-star" style="color:#d1d5db;margin-right:4px;"></i>No ratings yet</span>';
                        return;
                    }
                    const stars = starsHtml(data.avg_rating);
                    el.innerHTML = `
                        <span class="cp-rating-badge">
                            ${stars}
                            <strong>${data.avg_rating}</strong>
                            <span class="count">(${data.total_ratings} review${data.total_ratings !== 1 ? 's' : ''})</span>
                        </span>`;
                });
        },

        /**
         * Render a reviews list into an element.
         */
        renderReviews(containerId, userId) {
            const el = document.getElementById(containerId);
            if (!el) return;
            fetch(`get_ratings.php?user_id=${userId}`, { credentials: 'same-origin' })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) { el.innerHTML = ''; return; }

                    let html = `<div class="cp-reviews-section">
                        <h3><i class="fas fa-star" style="color:#f59e0b"></i> Reviews (${data.total_ratings})</h3>`;

                    if (data.reviews.length === 0) {
                        html += `<div class="cp-no-reviews">
                            <i class="fas fa-comment-slash"></i>
                            No reviews yet. Be the first to rate this trader!
                        </div>`;
                    } else {
                        data.reviews.forEach(rv => {
                            const d = new Date(rv.updated_at).toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'numeric' });
                            html += `
                            <div class="cp-review-card">
                                <div class="cp-review-top">
                                    <img src="${escHtml(rv.rater_picture)}" alt="${escHtml(rv.rater_name)}">
                                    <span class="rater-name">${escHtml(rv.rater_name)}</span>
                                    <span class="review-date">${d}</span>
                                </div>
                                <div class="cp-stars sm">${starsHtml(rv.rating)}</div>
                                ${rv.comment ? `<div class="cp-review-comment">${escHtml(rv.comment)}</div>` : ''}
                            </div>`;
                        });
                    }
                    html += '</div>';
                    el.innerHTML = html;
                });
        }
    };

    // ── Helpers ─────────────────────────────────────────────────
    function starsHtml(avg) {
        let html = '<span class="cp-stars">';
        for (let i = 1; i <= 5; i++) {
            html += `<span class="star${i <= Math.round(avg) ? ' filled' : ''}">★</span>`;
        }
        html += '</span>';
        return html;
    }

    function escHtml(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // Close on Escape
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') CyclePointRating.closeModal();
    });

})();