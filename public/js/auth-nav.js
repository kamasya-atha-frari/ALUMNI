/**
 * AlumniIstü Dynamic Role-Based Navigation Engine
 *
 * Queries GET /api/auth/me (protected by AuthMiddleware) on page load so navigation
 * reflects the authoritative server authentication state and user role:
 *   - Guest (unauthenticated / expired / invalid / revoked token):
 *       Directory, Job Board, Career Graph, Faculty, API Docs + Login & Register
 *   - Regular Authenticated User (alumni / student / employer / faculty / moderator):
 *       Directory, Job Board, Career Graph, My Profile, Faculty, API Docs + @user badge & Logout
 *   - Admin (role === 'admin'):
 *       All regular user links PLUS Admin Panel (/admin) link & Logout
 *
 * Always generates root-relative paths (/jobs, /analytics, /about, /swagger,
 * /my-profile, /admin, /login, /register) regardless of current route (e.g. /alumni/1),
 * clears existing static nav items before rendering to prevent duplicates, and
 * keeps menu items wrap-safe and compact so nothing overflows the header.
 */
(function () {
    const TOKEN_KEY = 'alumni_istu_access_token';
    const USER_KEY = 'alumni_istu_user';

    function normalizedPath() {
        const raw = window.location.pathname || '/';
        const cleaned = raw.replace(/\/+$/, '');
        return cleaned === '' ? '/' : cleaned;
    }

    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"']/g, (m) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[m]));
    }

    function clearLocalAuth() {
        try {
            localStorage.removeItem(TOKEN_KEY);
            localStorage.removeItem(USER_KEY);
        } catch (_) { }
    }

    function isLinkActive(href, currentPath) {
        if (href === '/directory') {
            return currentPath === '/directory' || currentPath === '/dashboard' || currentPath === '/alumni' || currentPath.startsWith('/alumni/');
        }
        if (href === '/my-profile') {
            return currentPath === '/my-profile' || currentPath === '/profile';
        }
        return currentPath === href;
    }

    function buildLink(href, label, currentPath, styleClass, extraClass) {
        const active = isLinkActive(href, currentPath) ? ' active' : '';
        const extra = extraClass ? ` ${extraClass}` : '';
        return `<a href="${href}" class="${styleClass}${active}${extra}">${label}</a>`;
    }

    function renderMenus(state) {
        const current = normalizedPath();
        const isAuth = Boolean(state && state.authenticated && state.user);
        const user = isAuth ? state.user : null;
        const role = isAuth ? String(user.role || '').toLowerCase() : 'guest';
        const isAdmin = isAuth && role === 'admin';

        // 1. Primary navigation links container(s): [data-auth-nav-links]
        document.querySelectorAll('[data-auth-nav-links]').forEach((navEl) => {
            const linkClass = navEl.getAttribute('data-link-class') || 'mercury-nav-pill';
            const links = [
                buildLink('/directory', 'Directory', current, linkClass, ''),
                buildLink('/jobs', 'Job Board', current, linkClass, ''),
                buildLink('/analytics', 'Career Graph', current, linkClass, ''),
            ];

            if (isAuth) {
                links.push(buildLink('/my-profile', 'My Profile', current, linkClass, ''));
            }

            links.push(buildLink('/about', 'Faculty', current, linkClass, ''));
            links.push(buildLink('/swagger', 'API Docs', current, linkClass, ''));

            if (isAdmin) {
                links.push(
                    buildLink(
                        '/admin',
                        '<i class="fa-solid fa-shield-halved text-xs" aria-hidden="true"></i><span>Admin Panel</span>',
                        current,
                        linkClass,
                        'nav-admin-link'
                    )
                );
            }

            // Clear/replace any pre-existing static links to prevent duplication
            navEl.replaceChildren();
            navEl.innerHTML = links.join('');
        });

        // 2. Right-side auth action controls container(s): [data-auth-nav-actions]
        document.querySelectorAll('[data-auth-nav-actions]').forEach((actionsEl) => {
            actionsEl.replaceChildren();

            if (!isAuth) {
                actionsEl.innerHTML = `
                    <a href="/login" class="auth-nav-login">Login</a>
                    <a href="/register" class="auth-nav-register">Register</a>
                `;
                return;
            }

            const handle = escapeHtml(user.username || user.full_name || 'user');
            const roleEsc = escapeHtml(role);
            const titleEsc = escapeHtml(`Signed in as ${user.email || user.username} (${role})`);

            actionsEl.innerHTML = `
                <a href="/my-profile" class="auth-nav-user-pill" title="${titleEsc}">
                    <span class="auth-nav-dot" aria-hidden="true"></span>
                    <span>@${handle}</span>
                    <span class="auth-nav-role-tag">${roleEsc}</span>
                </a>
                <button type="button" data-auth-logout-btn class="auth-nav-logout">
                    <i class="fa-solid fa-right-from-bracket text-[11px]" aria-hidden="true"></i>
                    <span>Logout</span>
                </button>
            `;
        });

        // 3. Bind logout buttons dynamically
        document.querySelectorAll('[data-auth-logout-btn]').forEach((btn) => {
            btn.addEventListener('click', handleLogout);
        });

        // 4. Emit custom event so page scripts can react to authoritative auth state
        window.dispatchEvent(new CustomEvent('alumni:auth-state', {
            detail: { authenticated: isAuth, role, isAdmin, user }
        }));
    }

    async function handleLogout() {
        const token = localStorage.getItem(TOKEN_KEY) || '';
        const headers = { 'Accept': 'application/json' };
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        try {
            await fetch('/api/auth/logout', {
                method: 'POST',
                credentials: 'same-origin',
                headers
            });
        } catch (_) { }

        clearLocalAuth();
        renderMenus({ authenticated: false, user: null });

        const current = normalizedPath();
        if (current === '/admin' || current === '/my-profile' || current === '/profile') {
            window.location.href = '/login';
        }
    }

    async function refresh() {
        // Render safe guest state first so admin links never flash for unauthorized users
        renderMenus({ authenticated: false, user: null });

        const token = localStorage.getItem(TOKEN_KEY) || '';
        const headers = { 'Accept': 'application/json' };
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        try {
            const res = await fetch('/api/auth/me', {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers
            });

            if (!res.ok) {
                // Expired, invalid, revoked, or inactive token -> reset cleanly to guest
                clearLocalAuth();
                renderMenus({ authenticated: false, user: null });
                return { authenticated: false, role: 'guest', user: null };
            }

            const payload = await res.json();
            if (payload && payload.status === 'success' && payload.data && payload.data.user) {
                const user = payload.data.user;
                try {
                    localStorage.setItem(USER_KEY, JSON.stringify(user));
                } catch (_) { }
                renderMenus({ authenticated: true, user });
                return {
                    authenticated: true,
                    role: user.role,
                    isAdmin: user.role === 'admin',
                    user
                };
            }

            clearLocalAuth();
            renderMenus({ authenticated: false, user: null });
            return { authenticated: false, role: 'guest', user: null };
        } catch (_) {
            // Network/server error -> stay in safe guest state
            renderMenus({ authenticated: false, user: null });
            return { authenticated: false, role: 'guest', user: null };
        }
    }

    window.AlumniAuthNav = {
        refresh,
        logout: handleLogout,
        clearLocalAuth
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', refresh);
    } else {
        refresh();
    }
})();
