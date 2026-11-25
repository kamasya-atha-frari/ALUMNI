/**
 * AlumniIstü Dynamic Role-Based Navigation Engine
 *
 * Queries GET /api/auth/me (protected by AuthMiddleware) on page load so navigation
 * reflects the authoritative server authentication state and user role:
 *   - Guest (unauthenticated / expired / invalid / revoked token):
 *       Home / Directory, Job Board, Career Graph, Faculty, API Docs + Login & Register
 *   - Regular Authenticated User (alumni / student / employer / faculty / moderator):
 *       Home / Directory, Job Board, Career Graph, My Profile, Faculty, API Docs + @user badge & Logout
 *   - Admin (role === 'admin'):
 *       All regular user links PLUS Admin Panel (/admin) link & Logout
 *
 * Unauthorized users never see admin-only links in the DOM, and expired/invalid tokens
 * automatically clear localStorage and reset navigation cleanly to guest state.
 */
(function () {
    const TOKEN_KEY = 'alumni_istu_access_token';
    const USER_KEY = 'alumni_istu_user';
    const BASE_PATH = window.location.pathname.toLowerCase().startsWith('/alumni/') ? '/alumni' : '';

    function withBase(path) {
        return `${BASE_PATH}${path}`;
    }

    function normalizedPath() {
        let p = window.location.pathname || '/';
        if (BASE_PATH && p.toLowerCase().startsWith(BASE_PATH)) {
            p = p.slice(BASE_PATH.length) || '/';
        }
        return p.replace(/\/+$/, '') || '/';
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
        } catch (_) {}
    }

    function buildLink(href, label, currentPath, styleClass, extraClass = '') {
        const active = currentPath === href ? ' active' : '';
        return `<a href="${withBase(href)}" class="${styleClass}${active} ${extraClass}">${label}</a>`;
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
                buildLink('/', 'Directory', current, linkClass),
                buildLink('/jobs', 'Job Board', current, linkClass),
                buildLink('/analytics', 'Career Graph', current, linkClass),
            ];

            if (isAuth) {
                const isProfileActive = current === '/my-profile' || current === '/dashboard' || current === '/profile';
                const activeCls = isProfileActive ? ' active' : '';
                links.push(`<a href="${withBase('/my-profile')}" class="${linkClass}${activeCls}">My Profile</a>`);
            }

            links.push(buildLink('/about', 'Faculty', current, linkClass));
            links.push(buildLink('/swagger', 'API Docs', current, linkClass));

            if (isAdmin) {
                links.push(
                    buildLink(
                        '/admin',
                        '<i class="fa-solid fa-shield-halved text-[#FF6B58] mr-1 text-xs"></i>Admin Panel',
                        current,
                        linkClass,
                        'border border-[#FF6B58]/40 text-[#FF6B58]'
                    )
                );
            }

            navEl.innerHTML = links.join('');
        });

        // 2. Right-side auth action controls container(s): [data-auth-nav-actions]
        document.querySelectorAll('[data-auth-nav-actions]').forEach((actionsEl) => {
            if (!isAuth) {
                actionsEl.innerHTML = `
                    <a href="${withBase('/login')}" class="text-sm text-[#F4EDE0]/85 hover:text-[#FF6B58] transition px-3 py-1.5 rounded-full">
                        Login
                    </a>
                    <a href="${withBase('/register')}" class="text-sm font-medium px-4 py-2 rounded-full bg-[#FF6B58] text-[#F4EDE0] hover:bg-[#ff7e6d] transition inline-flex items-center gap-1.5">
                        <span>Register</span>
                    </a>
                `;
                return;
            }

            const adminPill = isAdmin
                ? `<a href="${withBase('/admin')}" class="text-xs font-medium px-3 py-1.5 rounded-full bg-[#FF6B58]/20 border border-[#FF6B58]/50 text-[#FF6B58] hover:bg-[#FF6B58]/30 transition inline-flex items-center gap-1.5">
                        <i class="fa-solid fa-shield-halved text-[11px]"></i>
                        <span>Admin</span>
                   </a>`
                : '';

            actionsEl.innerHTML = `
                ${adminPill}
                <a href="${withBase('/my-profile')}" class="text-xs px-3 py-1.5 rounded-full bg-[#1c2a4a] border border-[#F4EDE0]/20 text-[#F4EDE0]/90 hover:border-[#FF6B58] transition inline-flex items-center gap-1.5" title="Signed in as ${escapeHtml(user.email || user.username)} (${escapeHtml(role)})">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                    <span>@${escapeHtml(user.username || user.full_name || 'user')}</span>
                    <span class="text-[10px] uppercase tracking-wider text-[#F4EDE0]/55">${escapeHtml(role)}</span>
                </a>
                <button type="button" data-auth-logout-btn class="text-xs px-3 py-1.5 rounded-full border border-[#FF6B58]/50 text-[#FF6B58] hover:bg-[#FF6B58]/15 transition cursor-pointer inline-flex items-center gap-1">
                    <i class="fa-solid fa-right-from-bracket text-[11px]"></i>
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
            await fetch(withBase('/api/auth/logout'), {
                method: 'POST',
                credentials: 'same-origin',
                headers
            });
        } catch (_) {}

        clearLocalAuth();
        renderMenus({ authenticated: false, user: null });

        const current = normalizedPath();
        if (current === '/admin' || current === '/my-profile' || current === '/dashboard' || current === '/profile') {
            window.location.href = withBase('/login');
        }
    }

    async function refresh() {
        // Render safe guest state first so admin links can never flash for unauthorized users
        renderMenus({ authenticated: false, user: null });

        const token = localStorage.getItem(TOKEN_KEY) || '';
        const headers = { 'Accept': 'application/json' };
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        try {
            const res = await fetch(withBase('/api/auth/me'), {
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
                } catch (_) {}
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
