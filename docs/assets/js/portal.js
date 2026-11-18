/*
 * BSU Faculty Portal: static demo core.
 *
 * A JavaScript port of the PHP app's includes/ folder. The sample records
 * (assets/js/data.js) are loaded into an in-memory store; every add, edit and
 * delete is kept in this browser's localStorage as a small list of changes, so
 * each visitor works on their own copy and can reset it at any time.
 */
(function () {
    'use strict';

    const P = window.Portal = {};

    /* ================================================================== */
    /* Settings                                                            */
    /* ================================================================== */

    const APP_NAME = 'BSU';
    const APP_TAGLINE = 'Faculty Portal';
    // The account every visitor uses. The static demo has no sign-in.
    const DEMO_ACCOUNT = { id: 1, name: 'Faculty Office', email: 'Static demo', role: 'University-wide access' };
    const EDITS_KEY = 'bsu-portal-demo-edits-v1';
    const FLASH_KEY = 'bsu-portal-demo-flash';
    const TERM_KEY = 'bsu-portal-demo-term';

    const REQUIREMENTS = ['Form 138 / Transcript', 'PSA birth certificate', 'Good moral certificate', 'Medical clearance', '2x2 ID photos'];
    const RISK_TYPES = ['Failing', 'INC', 'Behind curriculum'];
    const RISK_BACK_SUBJECTS = 3;
    const INTERVENTION_STATUSES = ['Not contacted', 'Contacted', 'Counseling scheduled', 'Improving'];
    const EVAL_CATEGORIES = [
        ['teaching', 'Teaching effectiveness'],
        ['organization', 'Course organization'],
        ['communication', 'Communication'],
        ['fairness', 'Fairness in grading'],
        ['punctuality', 'Punctuality and availability'],
    ];
    const EVAL_LABELS = EVAL_CATEGORIES.map(c => c[1]);
    const WEEK_DAYS = [['Mon', 'Monday'], ['Tue', 'Tuesday'], ['Wed', 'Wednesday'], ['Thu', 'Thursday'], ['Fri', 'Friday'], ['Sat', 'Saturday']];
    const DAY_ORDER = { Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 };
    const GRADE_VALUES = ['1.00', '1.25', '1.50', '1.75', '2.00', '2.25', '2.50', '2.75', '3.00', '5.00', 'INC', 'DRP'];

    Object.assign(P, { APP_NAME, APP_TAGLINE, REQUIREMENTS, RISK_TYPES, RISK_BACK_SUBJECTS, INTERVENTION_STATUSES, EVAL_CATEGORIES, EVAL_LABELS, WEEK_DAYS, GRADE_VALUES, DEMO_ACCOUNT });

    /* ================================================================== */
    /* Browser storage (every access can throw in private windows)         */
    /* ================================================================== */

    function sget(kind, key) {
        try { const s = window[kind]; return s ? s.getItem(key) : null; } catch (err) { return null; }
    }
    function sset(kind, key, value) {
        try {
            const s = window[kind];
            if (!s) return false;
            if (value === null) s.removeItem(key); else s.setItem(key, value);
            return true;
        } catch (err) { return false; }
    }
    P.sget = sget;
    P.sset = sset;

    /* ================================================================== */
    /* Record store: seed data + this browser's edits                      */
    /* ================================================================== */

    const PKS = {
        meta: ['k'], terms: ['id'], colleges: ['id'], programs: ['id'], rooms: ['id'], faculty: ['id'], courses: ['id'],
        students: ['id'], sections: ['id'], meetings: ['id'], enrollments: ['section_id', 'student_id'],
        interventions: ['student_id'], risk_snapshots: ['month', 'college_id'], evaluation_totals: ['section_id'],
        faculty_rating_history: ['faculty_id', 'term_id'], applicants: ['id'], applicant_requirements: ['applicant_id', 'requirement'],
    };
    const SEP = '\u0001';
    const state = { tables: {}, overlay: {}, log: null };

    const keyOf = (t, row) => PKS[t].map(k => row[k]).join(SEP);

    function loadStore() {
        const seed = window.PORTAL_SEED || {};
        for (const t of Object.keys(PKS)) {
            const map = new Map();
            const s = seed[t];
            if (s) {
                for (const r of s.r) {
                    const o = {};
                    for (let i = 0; i < s.c.length; i++) o[s.c[i]] = r[i];
                    map.set(keyOf(t, o), o);
                }
            }
            state.tables[t] = map;
        }
        let overlay = {};
        try { overlay = JSON.parse(sget('localStorage', EDITS_KEY) || '{}') || {}; } catch (err) { overlay = {}; }
        state.overlay = overlay;
        for (const t of Object.keys(overlay)) {
            const map = state.tables[t];
            if (!map) continue;
            for (const k of Object.keys(overlay[t])) {
                const row = overlay[t][k];
                if (row === null) map.delete(k); else map.set(k, row);
            }
        }
    }

    function remember(t, k) {
        if (!state.log) return;
        const ov = state.overlay[t];
        state.log.push({ t, k, had: state.tables[t].has(k), old: state.tables[t].get(k), hadOv: !!ov && k in ov, oldOv: ov ? ov[k] : undefined });
    }

    const DB = P.db = {
        all: t => Array.from(state.tables[t].values()),
        get: (t, ...pk) => state.tables[t].get(pk.join(SEP)) || null,
        find: (t, pred) => { for (const r of state.tables[t].values()) if (pred(r)) return r; return null; },
        count: (t, pred) => { let n = 0; for (const r of state.tables[t].values()) if (!pred || pred(r)) n++; return n; },
        put(t, row) {
            const k = keyOf(t, row);
            remember(t, k);
            const copy = Object.assign({}, row);
            state.tables[t].set(k, copy);
            (state.overlay[t] = state.overlay[t] || {})[k] = copy;
            return copy;
        },
        del(t, ...pk) {
            const k = pk.join(SEP);
            if (!state.tables[t].has(k)) return false;
            remember(t, k);
            state.tables[t].delete(k);
            (state.overlay[t] = state.overlay[t] || {})[k] = null;
            return true;
        },
        delWhere(t, pred) {
            let n = 0;
            for (const row of Array.from(state.tables[t].values())) {
                if (pred(row)) { DB.del(t, ...PKS[t].map(c => row[c])); n++; }
            }
            return n;
        },
        nextId(t) {
            let max = 0;
            for (const r of state.tables[t].values()) if (r.id > max) max = r.id;
            return max + 1;
        },
        begin() { state.log = []; },
        commit() { state.log = null; },
        rollback() {
            const log = state.log || [];
            state.log = null;
            for (let i = log.length - 1; i >= 0; i--) {
                const x = log[i];
                if (x.had) state.tables[x.t].set(x.k, x.old); else state.tables[x.t].delete(x.k);
                if (x.hadOv) state.overlay[x.t][x.k] = x.oldOv; else if (state.overlay[x.t]) delete state.overlay[x.t][x.k];
            }
        },
        save: () => sset('localStorage', EDITS_KEY, JSON.stringify(state.overlay)),
        hasEdits: () => Object.keys(state.overlay).some(t => Object.keys(state.overlay[t]).length > 0),
        reset: () => sset('localStorage', EDITS_KEY, null),
    };

    loadStore();

    /* ================================================================== */
    /* Dates. Stored times are Manila wall-clock times; they are handled   */
    /* as UTC everywhere so no time-zone conversion ever happens.          */
    /* ================================================================== */

    const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    const MONTH_NAMES = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    const pad2 = n => (n < 10 ? '0' : '') + n;

    /** 'YYYY-MM-DD' or 'YYYY-MM-DD HH:MM:SS' -> seconds. */
    function strtotime(s) {
        if (!s) return null;
        const m = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/.exec(String(s));
        if (!m) return null;
        return Date.UTC(+m[1], +m[2] - 1, +m[3], +(m[4] || 0), +(m[5] || 0), +(m[6] || 0)) / 1000;
    }

    /** A subset of PHP's date() format characters. */
    function date(fmt, ts) {
        const d = new Date(ts * 1000);
        const Y = d.getUTCFullYear(), mo = d.getUTCMonth(), day = d.getUTCDate(), w = d.getUTCDay();
        const h = d.getUTCHours(), mi = d.getUTCMinutes(), s = d.getUTCSeconds();
        let out = '';
        for (const ch of fmt) {
            switch (ch) {
                case 'Y': out += Y; break;
                case 'm': out += pad2(mo + 1); break;
                case 'n': out += mo + 1; break;
                case 'd': out += pad2(day); break;
                case 'j': out += day; break;
                case 'D': out += DAY_NAMES[w].slice(0, 3); break;
                case 'l': out += DAY_NAMES[w]; break;
                case 'F': out += MONTH_NAMES[mo]; break;
                case 'M': out += MONTH_NAMES[mo].slice(0, 3); break;
                case 'G': out += h; break;
                case 'H': out += pad2(h); break;
                case 'g': out += (h % 12) || 12; break;
                case 'i': out += pad2(mi); break;
                case 's': out += pad2(s); break;
                case 'A': out += h < 12 ? 'AM' : 'PM'; break;
                default: out += ch;
            }
        }
        return out;
    }

    const meta = (key, def = null) => { const r = DB.get('meta', key); return r ? r.v : def; };
    const app_now = () => { const asOf = meta('as_of'); return asOf ? strtotime(asOf) : Math.floor(Date.now() / 1000); };
    const now_sql = () => date('Y-m-d H:i:s', app_now());

    Object.assign(P, { strtotime, date, meta, app_now });

    /* ================================================================== */
    /* Small utilities                                                     */
    /* ================================================================== */

    const e = v => String(v === null || v === undefined ? '' : v)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');

    /** MySQL utf8mb4_general_ci ordering: case- and accent-insensitive, trailing spaces ignored. */
    const ciKeys = new Map();
    function ciKey(s) {
        s = s === null || s === undefined ? '' : String(s);
        let k = ciKeys.get(s);
        if (k === undefined) {
            k = s.normalize('NFD').replace(/[̀-ͯ]/g, '').toUpperCase().replace(/ +$/, '');
            ciKeys.set(s, k);
        }
        return k;
    }
    const cmpStr = (a, b) => { const x = ciKey(a), y = ciKey(b); return x < y ? -1 : (x > y ? 1 : 0); };
    /** PHP's <=> for scalars and arrays of scalars. */
    function cmp(a, b) {
        if (Array.isArray(a)) {
            for (let i = 0; i < a.length; i++) { const c = cmp(a[i], b[i]); if (c) return c; }
            return 0;
        }
        if (typeof a === 'string' && typeof b === 'string') return a < b ? -1 : (a > b ? 1 : 0);
        if (typeof a === 'boolean') a = +a;
        if (typeof b === 'boolean') b = +b;
        return a < b ? -1 : (a > b ? 1 : 0);
    }

    function number_format(n, decimals = 0) {
        n = Number(n) || 0;
        const out = n.toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
        return /^-0(\.0+)?$/.test(out) ? out.slice(1) : out;
    }
    /** PHP's round(): decimal half away from zero, so round(3.745, 2) is 3.75. */
    function round(n, d = 0) {
        n = Number(n);
        if (!isFinite(n)) return n;
        const sign = n < 0 ? -1 : 1, abs = Math.abs(n);
        if (/e/i.test(String(abs))) { const f = Math.pow(10, d); return sign * Math.round(abs * f) / f; }
        return sign * Number(Math.round(Number(abs + 'e' + d)) + 'e-' + d);
    }
    const sum = list => list.reduce((a, b) => a + (Number(b) || 0), 0);
    function count_values(list) {
        const out = {};
        for (const v of list) { if (v === null || v === undefined) continue; out[v] = (out[v] || 0) + 1; }
        return out;
    }
    const pct = (part, whole, decimals = 0) => whole > 0 ? number_format(part / whole * 100, decimals) : '0';

    let crcTable = null;
    function crc32(str) {
        if (!crcTable) {
            crcTable = [];
            for (let n = 0; n < 256; n++) {
                let c = n;
                for (let k = 0; k < 8; k++) c = c & 1 ? 0xEDB88320 ^ (c >>> 1) : c >>> 1;
                crcTable[n] = c >>> 0;
            }
        }
        const bytes = new TextEncoder().encode(str);
        let crc = 0xFFFFFFFF;
        for (const b of bytes) crc = (crc >>> 8) ^ crcTable[(crc ^ b) & 0xFF];
        return (crc ^ 0xFFFFFFFF) >>> 0;
    }

    Object.assign(P, { e, cmp, cmpStr, number_format, round, sum, count_values, pct });

    /* ================================================================== */
    /* Query string and page URLs                                          */
    /* ================================================================== */

    const params = new URLSearchParams(location.search);
    const PAGE_FILE = (location.pathname.split('/').pop() || 'index.html');

    const q = (key, def = '') => { const v = params.get(key); return v === null ? def : v.trim(); };
    const enc = s => encodeURIComponent(s).replace(/%20/g, '+');
    const buildQuery = obj => Object.keys(obj).map(k => enc(k) + '=' + enc(String(obj[k]))).join('&');
    function currentParams() {
        const out = {};
        for (const [k, v] of params) out[k] = v;
        return out;
    }

    /** Current page URL with some params changed. Changing filters resets the page number. */
    function url_with(changes = {}, page = null) {
        const p = Object.assign(currentParams(), changes);
        if (!('p' in changes)) delete p.p;
        for (const k of Object.keys(p)) if (p[k] === '' || p[k] === null || p[k] === undefined || p[k] === 'all') delete p[k];
        const qs = buildQuery(p);
        return (page || PAGE_FILE) + (qs ? '?' + qs : '');
    }

    /** Where a form should return to: the current page with its filters. */
    function return_url(anchor = '#list') {
        const p = currentParams();
        delete p.export;
        const qs = buildQuery(p);
        return PAGE_FILE + (qs ? '?' + qs : '') + anchor;
    }

    function go(to) {
        const u = new URL(to, location.href);
        if (u.pathname === location.pathname && u.search === location.search) {
            history.replaceState(null, '', u.href);
            location.reload();
        } else {
            location.href = u.href;
        }
    }

    Object.assign(P, { q, url_with, return_url, go, currentParams, buildQuery, PAGE_FILE });

    /* ================================================================== */
    /* Sign-in, roles and messages                                         */
    /* ================================================================== */

    /** The static demo has no sign-in: every visitor uses the demo account. */
    const current_user = () => Object.assign({}, DEMO_ACCOUNT);

    function role_permissions(role) {
        switch (role) {
            case 'University-wide access': return ['academic.view', 'academic.manage', 'sete.view', 'sete.manage', 'prediction.demo'];
            case 'Academic Viewer': return ['academic.view', 'sete.view', 'prediction.demo'];
            case 'Faculty': return ['prediction.demo'];
            default: return [];
        }
    }
    function can(permission, user) {
        user = user || current_user();
        return !!user && role_permissions(String(user.role || '')).includes(permission);
    }
    function action_permission(action) {
        switch (action) {
            case 'catalog_save': return 'academic.manage';
            case 'faculty_save': case 'faculty_delete': return 'sete.manage';
            case 'applicant_save': case 'applicant_delete': case 'student_save': case 'student_delete':
            case 'section_save': case 'section_delete': case 'grades_save': case 'enrollment_add': case 'enrollment_remove':
            case 'intervention_save': case 'intervention_delete': return 'academic.manage';
            default: return 'denied';
        }
    }
    const home_for_user = user => can('academic.view', user) ? 'index.html' : 'predictions.html';

    const require_login = () => current_user();
    function require_permission(permission, user) {
        if (!can(permission, user)) {
            document.body.innerHTML = '<main class="content"><p>Access denied. Your account role does not authorize this feature.</p></main>';
            throw new P.Halt();
        }
    }

    function flash(type, text) { sset('sessionStorage', FLASH_KEY, JSON.stringify({ type, text })); }
    function take_flash() {
        const raw = sget('sessionStorage', FLASH_KEY);
        sset('sessionStorage', FLASH_KEY, null);
        try { return raw ? JSON.parse(raw) : null; } catch (err) { return null; }
    }

    P.Halt = function Halt() {};
    Object.assign(P, { current_user, can, action_permission, home_for_user, require_login, require_permission, flash });

    /* ================================================================== */
    /* Terms                                                               */
    /* ================================================================== */

    const memo = fn => { let done = false, value; return () => { if (!done) { value = fn(); done = true; } return value; }; };

    function academic_year(term) {
        let year = +term.starts_on.slice(0, 4);
        if (+term.starts_on.slice(5, 7) < 6) year--;
        return year + '-' + (year + 1);
    }
    const all_terms = () => DB.all('terms').sort((a, b) => cmp(b.starts_on, a.starts_on));
    const active_term = memo(() => DB.find('terms', t => t.is_current === 1) || {
        id: 0, name: 'No active term', starts_on: date('Y-m-d', app_now()), ends_on: date('Y-m-d', app_now()), grade_deadline: date('Y-m-d', app_now()),
    });
    const current_term = memo(() => {
        const terms = all_terms();
        const raw = params.get('term');
        const requested = raw !== null && /^\d+$/.test(raw) ? +raw : (+(sget('sessionStorage', TERM_KEY) || 0) || active_term().id);
        const ay = params.get('ay') || '';
        const scope = terms.filter(t => ay === '' || academic_year(t) === ay);
        let term = scope.find(t => t.id === requested) || scope[0] || active_term();
        sset('sessionStorage', TERM_KEY, String(term.id));
        return term;
    });
    const is_historical_term = () => current_term().id !== active_term().id;
    const previous_term = memo(() => all_terms().find(t => t.starts_on < current_term().starts_on) || null);
    const intake_label = () => meta('intake_label', 'Incoming academic year');

    Object.assign(P, { academic_year, all_terms, active_term, current_term, is_historical_term, previous_term, intake_label });

    /* ================================================================== */
    /* Reference catalogs                                                  */
    /* ================================================================== */

    const programById = memo(() => new Map(DB.all('programs').map(p => [p.id, p])));
    const collegeById = memo(() => new Map(DB.all('colleges').map(c => [c.id, c])));
    const courseById = memo(() => new Map(DB.all('courses').map(c => [c.id, c])));
    const facultyById = memo(() => new Map(DB.all('faculty').map(f => [f.id, f])));
    const roomById = memo(() => new Map(DB.all('rooms').map(r => [r.id, r])));
    const sectionById = memo(() => new Map(DB.all('sections').map(s => [s.id, s])));

    /** { short name: { id, code, name, building, programs: [[code, name]...], rooms: [names] } } in id order. */
    const colleges = memo(() => {
        const list = new Map();
        const cols = DB.all('colleges').sort((a, b) => a.id - b.id);
        for (const c of cols) list.set(c.short_name, { id: c.id, code: c.code, name: c.name, building: c.building, programs: [], rooms: [] });
        const byId = new Map(cols.map(c => [c.id, c.short_name]));
        for (const p of DB.all('programs').sort((a, b) => a.id - b.id)) { const k = byId.get(p.college_id); if (k) list.get(k).programs.push([p.code, p.name]); }
        for (const r of DB.all('rooms').sort((a, b) => a.id - b.id)) { const k = byId.get(r.college_id); if (k) list.get(k).rooms.push(r.name); }
        return list;
    });
    const college_names = () => Array.from(colleges().keys());
    const valid_college = name => colleges().has(name) ? name : '';

    const person_name = (first, middle, last) => first + (middle ? ' ' + Array.from(middle)[0] + '.' : '') + ' ' + last;
    const section_year = section => { const m = /(\d)[A-Z]$/.exec(section); return m ? +m[1] : 1; };
    const year_label = y => ({ 1: '1st year', 2: '2nd year', 3: '3rd year' })[y] || y + 'th year';
    const faculty_name = f => f ? f.title + ' ' + f.first_name + ' ' + f.last_name : null;
    const termSection = x => x && x.term_id === current_term().id;

    Object.assign(P, { colleges, college_names, valid_college, person_name, section_year, year_label });

    /* ================================================================== */
    /* Students                                                            */
    /* ================================================================== */

    const students = memo(() => {
        const term = current_term().id;
        const hist = is_historical_term();
        const secs = sectionById(), courses = courseById();
        const agg = new Map();
        for (const en of DB.all('enrollments')) {
            const x = secs.get(en.section_id);
            if (!x || x.term_id !== term) continue;
            let a = agg.get(en.student_id);
            if (!a) { a = { units: 0, subjects: 0, year: null, block: null }; agg.set(en.student_id, a); }
            a.units += (courses.get(x.course_id) || {}).units || 0;
            a.subjects++;
            if (a.year === null || x.year_level < a.year) a.year = x.year_level;
            if (a.block === null || x.block < a.block) a.block = x.block;
        }
        const progs = programById(), cols = collegeById(), fac = facultyById();
        const rows = DB.all('students').filter(s => agg.has(s.id) || !hist);
        rows.sort((a, b) => cmpStr(a.last_name, b.last_name) || cmpStr(a.first_name, b.first_name) || cmpStr(a.middle_name, b.middle_name));
        return rows.map(s => {
            const a = agg.get(s.id);
            const p = progs.get(s.program_id) || {};
            const year = hist && a ? a.year : s.year_level;
            const block = hist && a ? a.block : s.block;
            return {
                sid: s.id, id: s.student_no, name: person_name(s.first_name, s.middle_name, s.last_name),
                first: s.first_name, middle: s.middle_name || '', last: s.last_name, sex: s.sex, birthdate: s.birthdate, block,
                search: s.first_name + ' ' + (s.middle_name || '') + ' ' + s.last_name + ' ' + s.last_name + ', ' + s.first_name,
                email: s.email, program: p.code, program_name: p.name, college: (cols.get(p.college_id) || {}).short_name,
                year, section: p.code + ' ' + year + block, status: s.status, back: s.back_subjects,
                units: a ? a.units : 0, subjects: a ? a.subjects : 0,
                adviser: faculty_name(fac.get(s.adviser_id)), enrolled_at: s.enrolled_at ? strtotime(s.enrolled_at) : null,
            };
        });
    });

    /* ================================================================== */
    /* At-risk students (derived from current-term grades)                 */
    /* ================================================================== */

    const at_risk = memo(() => {
        const term = current_term().id;
        const hist = is_historical_term();
        const secs = sectionById();
        const g = new Map();
        for (const en of DB.all('enrollments')) {
            const x = secs.get(en.section_id);
            if (!x || x.term_id !== term) continue;
            let a = g.get(en.student_id);
            if (!a) { a = { failing: 0, inc: 0, total: 0, n: 0 }; g.set(en.student_id, a); }
            const grade = en.final_grade !== null && en.final_grade !== undefined ? en.final_grade : en.midterm_grade;
            if (grade === '5.00') a.failing++;
            if (grade === 'INC') a.inc++;
            if (grade !== null && grade !== undefined && /^[0-9]/.test(grade)) { a.total += parseFloat(grade); a.n++; }
        }
        const byId = new Map(students().map(s => [s.sid, s]));
        const list = [];
        for (const st of DB.all('students')) {
            const a = g.get(st.id);
            const failing = a ? a.failing : 0, inc = a ? a.inc : 0;
            if (!(failing > 0 || inc > 0 || (st.status === 'Irregular' && st.back_subjects >= RISK_BACK_SUBJECTS && !hist))) continue;
            const s = byId.get(st.id);
            if (!s) continue;
            const back = hist ? 0 : st.back_subjects;
            const details = [];
            if (failing) details.push(failing + ' failing subject' + (failing > 1 ? 's' : ''));
            if (inc) details.push(inc + ' incomplete' + (inc > 1 ? 's' : ''));
            if (back >= RISK_BACK_SUBJECTS) details.push(back + ' back subjects');
            const iv = DB.get('interventions', st.id);
            list.push(Object.assign({}, s, {
                type: failing ? 'Failing' : (inc ? 'INC' : 'Behind curriculum'),
                detail: details.join(' · '), failing, inc,
                gwa: a && a.n ? round(a.total / a.n, 2) : null,
                priority: (failing >= 2 || (failing && inc) || back >= 5) ? 'Urgent' : 'Monitor',
                intervention: hist ? 'Not archived for term' : (iv ? iv.status : 'Not contacted'),
                last_contact: !hist && iv && iv.last_contact_at ? strtotime(iv.last_contact_at) : null,
                notes: iv ? iv.notes : null,
            }));
        }
        list.sort((a, b) => cmp([a.priority !== 'Urgent', -a.failing], [b.priority !== 'Urgent', -b.failing]) || cmp(a.name, b.name));
        return list;
    });
    const at_risk_ids = memo(() => new Set(at_risk().map(r => r.id)));

    /** Coverage check: no grades is unavailable evidence, not evidence of zero risk. */
    const evidenceCounts = {};
    function grade_evidence_count(college = '') {
        if (college in evidenceCounts) return evidenceCounts[college];
        const secs = sectionById(), progs = programById(), cols = collegeById();
        let n = 0;
        for (const en of DB.all('enrollments')) {
            const x = secs.get(en.section_id);
            if (!termSection(x)) continue;
            if (en.midterm_grade === null && en.final_grade === null) continue;
            if (college !== '') {
                const p = progs.get(x.program_id);
                if (!p || (cols.get(p.college_id) || {}).short_name !== college) continue;
            }
            n++;
        }
        return evidenceCounts[college] = n;
    }

    /** Month-end at-risk counts for the current term, plus the live count for this month. */
    function risk_history(college = '') {
        const term = current_term();
        const cols = collegeById();
        const months = {};
        const snaps = DB.all('risk_snapshots')
            .filter(r => r.month >= term.starts_on && r.month <= term.ends_on && (college === '' || (cols.get(r.college_id) || {}).short_name === college))
            .sort((a, b) => cmp(a.month, b.month));
        for (const r of snaps) months[r.month] = (months[r.month] || 0) + r.at_risk;
        const out = [];
        for (const m of Object.keys(months)) out.push([date('M Y', strtotime(m)), months[m]]);
        const live = college === '' ? at_risk().length : at_risk().filter(r => r.college === college).length;
        if (live > 0 || grade_evidence_count(college) > 0) {
            const label = date('M Y', is_historical_term() ? strtotime(term.ends_on) : app_now());
            const at = out.findIndex(x => x[0] === label);
            if (at > -1) out[at][1] = live; else out.push([label, live]);
        }
        return out;
    }

    /* ================================================================== */
    /* Applicants                                                          */
    /* ================================================================== */

    const applicants = memo(() => {
        const missing = new Map();
        for (const m of DB.all('applicant_requirements')) {
            if (m.submitted_at !== null) continue;
            if (!missing.has(m.applicant_id)) missing.set(m.applicant_id, []);
            missing.get(m.applicant_id).push(m.requirement);
        }
        for (const list of missing.values()) list.sort(cmp);
        const progs = programById(), cols = collegeById();
        return DB.all('applicants')
            .sort((a, b) => cmp(b.updated_at, a.updated_at) || b.id - a.id)
            .map(a => {
                const p = progs.get(a.program_id) || {};
                return {
                    aid: a.id, id: a.applicant_no, name: person_name(a.first_name, a.middle_name, a.last_name),
                    first: a.first_name, middle: a.middle_name || '', last: a.last_name, sex: a.sex, email: a.email,
                    program: p.code, program_name: p.name, college: (cols.get(p.college_id) || {}).short_name,
                    status: a.status, type: a.type, strand: a.shs_strand, prior: a.prior_gwa, score: a.exam_score,
                    missing: missing.get(a.id) || [], submitted: strtotime(a.submitted_at), updated: strtotime(a.updated_at),
                };
            });
    });
    const count_for_review = () => DB.count('applicants', a => a.status === 'For review');

    /* ================================================================== */
    /* Faculty + evaluations                                               */
    /* ================================================================== */

    const faculty = memo(() => {
        const term = current_term().id;
        const prev = previous_term() ? previous_term().id : 0;
        const secs = sectionById(), courses = courseById(), cols = collegeById();
        const sec = new Map(), invited = new Map(), ev = new Map();
        for (const x of secs.values()) {
            if (x.term_id !== term || x.faculty_id === null) continue;
            const s = sec.get(x.faculty_id) || { sections: 0, units: 0 };
            s.sections++;
            s.units += (courses.get(x.course_id) || {}).units || 0;
            sec.set(x.faculty_id, s);
        }
        for (const en of DB.all('enrollments')) {
            const x = secs.get(en.section_id);
            if (!x || x.term_id !== term || x.mode === 'Off-campus' || x.faculty_id === null) continue;
            invited.set(x.faculty_id, (invited.get(x.faculty_id) || 0) + 1);
        }
        for (const t of DB.all('evaluation_totals')) {
            const x = secs.get(t.section_id);
            if (!x || x.term_id !== term || x.faculty_id === null) continue;
            const a = ev.get(x.faculty_id) || { responses: 0, teaching: 0, organization: 0, communication: 0, fairness: 0, punctuality: 0 };
            a.responses += t.responses;
            for (const [col] of EVAL_CATEGORIES) a[col] += t[col];
            ev.set(x.faculty_id, a);
        }
        return DB.all('faculty')
            .sort((a, b) => cmpStr(a.last_name, b.last_name) || cmpStr(a.first_name, b.first_name))
            .map(f => {
                const a = ev.get(f.id);
                const categories = {};
                // MySQL AVG() of whole numbers is a 4-decimal value; PHP then rounds it to 2.
                for (const [col, label] of EVAL_CATEGORIES) categories[label] = a && a.responses ? round(round(a[col] / a.responses, 4), 2) : 0;
                const responses = a ? a.responses : 0;
                const rating = responses ? round(sum(Object.values(categories)) / EVAL_CATEGORIES.length, 2) : 0;
                const h = prev ? DB.get('faculty_rating_history', f.id, prev) : null;
                const s = sec.get(f.id) || { sections: 0, units: 0 };
                return {
                    fid: f.id, name: f.title + ' ' + f.first_name + ' ' + f.last_name, title: f.title, first: f.first_name, last: f.last_name,
                    email: f.email, college: (cols.get(f.college_id) || {}).short_name, rank: f.rank, employment: f.employment,
                    sections: s.sections, units: s.units, responses, enrolled: invited.get(f.id) || 0, rating,
                    previous: h ? h.rating : rating, has_previous: !!h, categories,
                };
            });
    });

    /* ================================================================== */
    /* Class sections, meetings and rooms                                  */
    /* ================================================================== */

    const minutes = time => { const [h, m] = String(time).split(':').map(Number); return h * 60 + m; };

    function clock(mins, meridiem = false) {
        if (mins === null || mins === undefined) return '—';
        const h = Math.floor(mins / 60), m = mins % 60;
        const out = ((h % 12) || 12) + ':' + pad2(m);
        return meridiem ? out + (h < 12 ? ' AM' : ' PM') : out;
    }

    /** "MWF 8:00–9:00 AM · Sat 1:00–4:00 PM" */
    function schedule_text(meetings) {
        const groups = new Map();
        for (const m of meetings) {
            const key = m.start + '-' + m.end;
            if (!groups.has(key)) groups.set(key, { days: [], start: m.start, end: m.end });
            groups.get(key).days.push(m.day === 'Thu' ? 'Th' : m.day.slice(0, m.day === 'Sat' ? 3 : 1));
        }
        const parts = Array.from(groups.values()).map(g => g.days.join('') + ' ' + clock(g.start) + '–' + clock(g.end, true));
        return parts.length ? parts.join(' · ') : 'No scheduled meetings';
    }

    const classes = memo(() => {
        const term = current_term().id;
        const rooms = roomById(), courses = courseById(), progs = programById(), cols = collegeById(), fac = facultyById();
        const meetings = new Map();
        const secs = sectionById();
        const termMeetings = DB.all('meetings').filter(m => termSection(secs.get(m.section_id)))
            .sort((a, b) => (DAY_ORDER[a.day] - DAY_ORDER[b.day]) || cmp(a.start_time, b.start_time));
        for (const m of termMeetings) {
            if (!meetings.has(m.section_id)) meetings.set(m.section_id, []);
            const r = m.room_id !== null ? rooms.get(m.room_id) : null;
            meetings.get(m.section_id).push({ day: m.day, start: minutes(m.start_time), end: minutes(m.end_time), room: r ? r.name : null });
        }
        const counts = new Map();
        for (const en of DB.all('enrollments')) {
            const c = counts.get(en.section_id) || { students: 0, encoded: 0 };
            c.students++;
            if (en.final_grade !== null) c.encoded++;
            counts.set(en.section_id, c);
        }
        const list = [];
        for (const x of secs.values()) {
            if (x.term_id !== term) continue;
            const co = courses.get(x.course_id), p = progs.get(x.program_id);
            if (!co || !p) continue;
            const c = counts.get(x.id) || { students: 0, encoded: 0 };
            const section = p.code + ' ' + x.year_level + x.block;
            const mtgs = meetings.get(x.id) || [];
            const f = x.faculty_id !== null ? fac.get(x.faculty_id) : null;
            list.push({
                id: x.id, key: co.code + ' · ' + section, code: co.code, title: co.title, units: co.units, kind: co.kind, section,
                course_id: x.course_id, faculty_id: f ? x.faculty_id : null, program: p.code, year: x.year_level, block: x.block,
                platform: x.platform, college: (cols.get(co.college_id) || {}).short_name, faculty: faculty_name(f) || 'TBA',
                students: c.students, mode: x.mode,
                room: x.mode === 'Online' ? x.platform : ((mtgs[0] && mtgs[0].room) || (x.mode === 'Off-campus' ? 'Partner site' : 'TBA')),
                meetings: mtgs, days: Array.from(new Set(mtgs.map(m => m.day))), schedule: schedule_text(mtgs),
                grade: x.grades_submitted_at ? 'Submitted' : (c.encoded > 0 ? 'In progress' : 'Not started'),
                encoded: c.students ? Math.round(c.encoded / c.students * 100) : 0,
                updated: x.grades_updated_at ? strtotime(x.grades_updated_at) : null,
            });
        }
        list.sort((a, b) => cmpStr(a.code, b.code) || cmpStr(a.program, b.program) || (a.year - b.year) || cmpStr(a.block, b.block));
        return list;
    });
    const class_by_id = id => classes().find(c => c.id === id) || null;

    /** Every class meeting on a given day ("Mon".."Sat"), earliest first. */
    function sessions_on(day) {
        const list = [];
        for (const c of classes()) {
            for (const m of c.meetings) {
                if (m.day === day) list.push(Object.assign({}, c, { start: m.start, end: m.end, room: c.mode === 'Online' ? c.room : (m.room || 'TBA') }));
            }
        }
        list.sort((a, b) => cmp([a.start, a.code, a.section], [b.start, b.code, b.section]));
        return list;
    }

    /** Pairs of class sections booked into the same room at overlapping times. */
    const room_conflicts = memo(() => {
        const secs = sectionById(), rooms = roomById();
        const byId = new Map(classes().map(c => [c.id, c]));
        const slots = new Map();
        for (const m of DB.all('meetings')) {
            if (m.room_id === null || !termSection(secs.get(m.section_id))) continue;
            const k = m.room_id + '|' + m.day;
            if (!slots.has(k)) slots.set(k, []);
            slots.get(k).push(m);
        }
        const found = [];
        for (const list of slots.values()) {
            for (const a of list) {
                for (const b of list) {
                    if (b.section_id <= a.section_id) continue;
                    if (a.start_time < b.end_time && b.start_time < a.end_time) found.push([a, b]);
                }
            }
        }
        found.sort((x, y) => DAY_ORDER[x[0].day] - DAY_ORDER[y[0].day]);
        const pairs = new Map();
        for (const [a, b] of found) {
            const key = a.section_id + '-' + b.section_id;
            if (!byId.has(a.section_id) || !byId.has(b.section_id)) continue;
            if (!pairs.has(key)) {
                pairs.set(key, {
                    room: (rooms.get(a.room_id) || {}).name, days: [],
                    start: minutes(a.start_time > b.start_time ? a.start_time : b.start_time),
                    end: minutes(a.end_time < b.end_time ? a.end_time : b.end_time),
                    a: byId.get(a.section_id), b: byId.get(b.section_id),
                });
            }
            pairs.get(key).days.push(a.day);
        }
        return Array.from(pairs.values());
    });

    /* ---------- Choices for the add / edit forms ---------- */

    /** grouped: [[college, [[code, "CODE · Program"]...]]...] */
    function program_choices() {
        return { grouped: Array.from(colleges()).map(([college, c]) => [college, c.programs.map(([code, name]) => [code, code + ' · ' + name])]).filter(g => g[1].length) };
    }
    function faculty_choices() {
        const out = new Map();
        for (const f of faculty()) {
            if (!out.has(f.college)) out.set(f.college, []);
            out.get(f.college).push([String(f.fid), f.name]);
        }
        return { grouped: Array.from(out).sort((a, b) => cmp(a[0], b[0])) };
    }
    const course_choices = () => DB.all('courses').sort((a, b) => cmpStr(a.code, b.code)).map(c => [String(c.id), c.code + ' · ' + c.title]);
    function room_choices() {
        const cols = collegeById();
        const out = new Map();
        const rooms = DB.all('rooms').sort((a, b) => (a.college_id - b.college_id) || cmpStr(a.name, b.name));
        for (const r of rooms) {
            const college = (cols.get(r.college_id) || {}).short_name;
            if (!out.has(college)) out.set(college, []);
            out.get(college).push([r.name, r.name + ' (' + r.type + ', ' + r.capacity + ' seats)']);
        }
        return Array.from(out);
    }

    /** Required reference catalogs; no fallback sample data is ever supplied. */
    const setup_missing = memo(() => {
        const missing = ['colleges', 'programs', 'courses', 'rooms'].filter(t => DB.count(t) === 0);
        if (!current_term().id) missing.push('active term');
        return missing;
    });
    function setup_missing_for_action(action) {
        const needed = {
            applicant_save: ['colleges', 'programs'],
            student_save: ['colleges', 'programs', 'active term'],
            faculty_save: ['colleges'],
            section_save: ['colleges', 'programs', 'courses', 'rooms', 'active term'],
            grades_save: ['active term'], enrollment_add: ['active term'], enrollment_remove: ['active term'],
        }[action] || [];
        return needed.filter(n => setup_missing().includes(n));
    }

    Object.assign(P, {
        students, at_risk, at_risk_ids, grade_evidence_count, risk_history, applicants, count_for_review, faculty,
        classes, class_by_id, sessions_on, room_conflicts, schedule_text, clock, minutes, setup_missing,
    });

    /* ================================================================== */
    /* Filtering, pagination and CSV export                                */
    /* ================================================================== */

    function search_rows(rows, term, fields) {
        if (term === '') return rows;
        const needle = term.toLowerCase();
        return rows.filter(row => fields.some(f => String(row[f] === null || row[f] === undefined ? '' : row[f]).toLowerCase().includes(needle)));
    }

    /** Keeps rows where each given field equals the value. Empty / "all" values are ignored. */
    function filter_rows(rows, equals) {
        const keys = Object.keys(equals).filter(k => equals[k] !== '' && equals[k] !== 'all' && equals[k] !== null && equals[k] !== undefined);
        if (!keys.length) return rows;
        return rows.filter(row => keys.every(k => String(row[k] === null || row[k] === undefined ? '' : row[k]) === String(equals[k])));
    }

    function paginate(rows, perPage = 20) {
        const total = rows.length;
        const pages = Math.max(1, Math.ceil(total / perPage));
        const page = Math.min(pages, Math.max(1, parseInt(q('p', '1'), 10) || 0));
        const offset = (page - 1) * perPage;
        return { items: rows.slice(offset, offset + perPage), page, pages, total, from: total ? offset + 1 : 0, to: Math.min(total, offset + perPage) };
    }

    function pagination(p, noun = 'records') {
        let html = '<div class="pager"><span class="pager-info">Showing <strong>' + number_format(p.from) + '–' + number_format(p.to)
            + '</strong> of <strong>' + number_format(p.total) + '</strong> ' + e(noun) + '</span>';
        if (p.pages > 1) {
            html += '<nav class="pager-links" aria-label="Pagination">';
            html += p.page > 1
                ? '<a href="' + e(url_with({ p: p.page - 1 }) + '#list') + '" aria-label="Previous page">' + icon('chev-l', 16) + '</a>'
                : '<span class="is-disabled">' + icon('chev-l', 16) + '</span>';
            const win = Array.from(new Set([1, p.page - 1, p.page, p.page + 1, p.pages].filter(n => n >= 1 && n <= p.pages))).sort((a, b) => a - b);
            let prev = 0;
            for (const n of win) {
                if (n - prev > 1) html += '<span class="pager-gap">…</span>';
                html += n === p.page
                    ? '<span class="is-current" aria-current="page">' + n + '</span>'
                    : '<a href="' + e(url_with({ p: n }) + '#list') + '">' + n + '</a>';
                prev = n;
            }
            html += p.page < p.pages
                ? '<a href="' + e(url_with({ p: p.page + 1 }) + '#list') + '" aria-label="Next page">' + icon('chev-r', 16) + '</a>'
                : '<span class="is-disabled">' + icon('chev-r', 16) + '</span>';
            html += '</nav>';
        }
        return html + '</div>';
    }

    /** Downloads the rows as CSV when ?export=csv is present, then drops the parameter from the address bar. */
    function maybe_export_csv(rows, columns, filename) {
        if (q('export') !== 'csv') return;
        const cell = v => {
            v = v === null || v === undefined ? '' : String(v);
            if (v !== '' && '=+-@'.includes(v[0])) v = "'" + v; // stop spreadsheet formulas
            return /[",\r\n\t ]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v;
        };
        const lines = [columns.map(c => cell(c[0])).join(',')];
        for (const row of rows) lines.push(columns.map(([, get]) => cell(typeof get === 'function' ? get(row) : row[get])).join(','));
        const blob = new Blob(['﻿' + lines.join('\n') + '\n'], { type: 'text/csv;charset=utf-8' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = filename + '-' + new Date().toISOString().slice(0, 10) + '.csv';
        document.documentElement.appendChild(a);
        a.click();
        setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
        const p = currentParams();
        delete p.export;
        const qs = buildQuery(p);
        history.replaceState(null, '', PAGE_FILE + (qs ? '?' + qs : '') + location.hash);
    }

    Object.assign(P, { search_rows, filter_rows, paginate, pagination, maybe_export_csv });

    /* ================================================================== */
    /* Icons                                                               */
    /* ================================================================== */

    const ICONS = {
        grid: '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user-plus': '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6"/><path d="M22 11h-6"/>',
        calendar: '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/>',
        'check-sq': '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="m8.5 12 2.5 2.5 5-5"/>',
        alert: '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        chart: '<path d="M3 21h18"/><path d="M6 17v-4"/><path d="M10 17V8"/><path d="M14 17v-6"/><path d="M18 17V5"/>',
        shield: '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
        search: '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        bell: '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
        'chev-r': '<path d="m9 18 6-6-6-6"/>',
        'chev-d': '<path d="m6 9 6 6 6-6"/>',
        'chev-l': '<path d="m15 18-6-6 6-6"/>',
        download: '<path d="M12 15V3"/><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/>',
        clock: '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        file: '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 13H8M16 17H8M16 13h-2"/>',
        book: '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H19a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H6.5a1 1 0 0 1 0-5H20"/>',
        'trend-up': '<path d="m22 7-8.5 8.5-5-5L2 17"/><path d="M16 7h6v6"/>',
        'user-x': '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m17 8 5 5M22 8l-5 5"/>',
        'arrow-up': '<path d="M12 19V5"/><path d="m5 12 7-7 7 7"/>',
        'arrow-dn': '<path d="M12 5v14"/><path d="m19 12-7 7-7-7"/>',
        pin: '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
        video: '<path d="m16 13 5.22 3.48a.5.5 0 0 0 .78-.42V7.87a.5.5 0 0 0-.75-.43L16 10.5"/><rect x="2" y="6" width="14" height="12" rx="2"/>',
        building: '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M16 6h.01M12 6h.01M12 10h.01M12 14h.01M16 10h.01M16 14h.01M8 10h.01M8 14h.01"/>',
        check: '<path d="M20 6 9 17l-5-5"/>',
        more: '<circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>',
        logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        menu: '<path d="M4 6h16M4 12h16M4 18h16"/>',
        x: '<path d="M18 6 6 18M6 6l12 12"/>',
        mail: '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
        lock: '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        eye: '<path d="M2.06 12.35a1 1 0 0 1 0-.7 10.75 10.75 0 0 1 19.88 0 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-19.88 0"/><circle cx="12" cy="12" r="3"/>',
        'eye-off': '<path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.53 13.53 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="m2 2 20 20"/>',
        user: '<circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/>',
        star: '<path d="M11.52 2.3a.53.53 0 0 1 .95 0l2.31 4.68a2.12 2.12 0 0 0 1.6 1.16l5.16.76a.53.53 0 0 1 .3.9l-3.74 3.64a2.12 2.12 0 0 0-.61 1.88l.88 5.14a.53.53 0 0 1-.77.56l-4.62-2.43a2.12 2.12 0 0 0-1.97 0L6.4 21.01a.53.53 0 0 1-.77-.56l.88-5.13a2.12 2.12 0 0 0-.61-1.88L2.16 9.8a.53.53 0 0 1 .3-.9l5.16-.76a2.12 2.12 0 0 0 1.6-1.16z"/>',
        plus: '<path d="M12 5v14M5 12h14"/>',
        edit: '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        trash: '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M10 11v6M14 11v6"/>',
        help: '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/>',
        graduation: '<path d="M21.42 10.92a1 1 0 0 0-.02-1.84L12.83 5.18a2 2 0 0 0-1.66 0L2.6 9.08a1 1 0 0 0 0 1.83l8.57 3.91a2 2 0 0 0 1.66 0z"/><path d="M22 10v6"/><path d="M6 12.5V16a6 3 0 0 0 12 0v-3.5"/>',
        refresh: '<path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 21v-5h5"/>',
    };
    function icon(name, size = 20, cls = '') {
        return '<svg' + (cls ? ' class="' + e(cls) + '"' : '') + ' width="' + size + '" height="' + size
            + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            + (ICONS[name] || '') + '</svg>';
    }
    P.icon = icon;

    /* ================================================================== */
    /* Display helpers                                                     */
    /* ================================================================== */

    function initials(name) {
        const parts = String(name).trim().split(/\s+/);
        let letters = '';
        for (const part of parts) if (part !== '' && /[A-Za-z]/.test(part[0])) letters += part[0].toUpperCase();
        if (letters === '') return '?';
        return letters.length === 1 ? letters : letters[0] + letters.slice(-1);
    }

    function select_filter(name, options, current, allLabel) {
        let html = '<div class="select select--sm"><select name="' + e(name) + '" data-autosubmit aria-label="' + e(allLabel) + '">';
        html += '<option value="">' + e(allLabel) + '</option>';
        for (const [value, label] of optPairs(options)) {
            html += '<option value="' + e(value) + '"' + (String(value) === current ? ' selected' : '') + '>' + e(label) + '</option>';
        }
        return html + '</select>' + icon('chev-d', 14) + '</div>';
    }

    /** Filter chips that link to the same page with one param changed. options = [[value, label]...] */
    function chip_links(param, options, current, counts = {}) {
        let html = '<div class="chips">';
        for (const [value, label] of options) {
            const active = String(value) === current;
            const count = counts[value] !== undefined ? ' <span class="chip-count">' + number_format(counts[value]) + '</span>' : '';
            html += '<a class="chip' + (active ? ' is-active' : '') + '" href="' + e(url_with({ [param]: value }) + '#list') + '">' + e(label) + count + '</a>';
        }
        return html + '</div>';
    }

    const tone_for = seed => ['purple', 'blue', 'orange', 'green', 'slate'][crc32(seed) % 5];

    function status_tone(status) {
        switch (status) {
            case 'Qualified': case 'Behind curriculum': case 'Off-campus': case 'Transferee': return 'purple';
            case 'For review': case 'Onsite': case 'Contacted': case 'Counseling scheduled': return 'blue';
            case 'Pending documents': case 'In progress': case 'INC': case 'Irregular': return 'orange';
            case 'Enrolled': case 'Submitted': case 'Online': case 'Regular': case 'Improving': return 'green';
            case 'Not qualified': case 'Failing': case 'Urgent': case 'Not started': return 'red';
            default: return 'slate';
        }
    }

    const tag = (label, tone = null, extra = '') => '<span class="tag tag--' + e(tone || status_tone(label)) + (extra ? ' ' + e(extra) : '') + '">' + e(label) + '</span>';

    function avatar(name, tone = null, extra = '') {
        const clean = String(name).replace(/^(Dr|Prof|Engr)\.\s+/, '');
        return '<span class="avatar avatar--' + e(tone || tone_for(name)) + (extra ? ' ' + e(extra) : '') + '">' + e(initials(clean)) + '</span>';
    }

    function rel_time(ts) {
        if (!ts) return '—';
        const day = date('Y-m-d', ts);
        if (day === date('Y-m-d', app_now())) return 'Today, ' + date('g:i A', ts);
        if (day === date('Y-m-d', app_now() - 86400)) return 'Yesterday, ' + date('g:i A', ts);
        return date('Y', ts) === date('Y', app_now()) ? date('M j, g:i A', ts) : date('M j, Y', ts);
    }

    function days_ago(ts) {
        if (!ts) return 'Never';
        const d = Math.round((strtotime(date('Y-m-d', app_now())) - strtotime(date('Y-m-d', ts))) / 86400);
        return d <= 0 ? 'Today' : (d === 1 ? 'Yesterday' : d + ' days ago');
    }

    const stars = rating => '<span class="stars" style="--r: ' + round(rating / 5 * 100, 1) + '%" aria-label="' + rating + ' out of 5">★★★★★</span>';

    /** Accepts [[k, v]...], a list of strings, or a plain object. */
    function optPairs(opts) {
        if (Array.isArray(opts)) return opts.map(o => (Array.isArray(o) ? [String(o[0]), o[1]] : [String(o), o]));
        return Object.keys(opts).map(k => [k, opts[k]]);
    }

    /* ---------- Charts ---------- */

    /** Horizontal bars. items = [[label, value] | [label, [value, displayText]]...]; links = { label: href }. */
    function bar_list(items, max = null, format = 'number', style = '', links = {}) {
        const values = items.map(([, v]) => (Array.isArray(v) ? v[0] : v));
        if (max === null) max = values.length ? Math.max(...values) : 1;
        const total = sum(values);
        const mods = style.split(' ').filter(Boolean);
        let html = '<ul class="bars' + mods.map(m => ' bars--' + e(m)).join('') + '">';
        for (const [label, item] of items) {
            const value = Array.isArray(item) ? item[0] : item;
            const shown = Array.isArray(item) ? e(item[1]) : (format === 'rating' ? number_format(value, 2) : (format === 'pct' ? Math.round(value) + '%' : number_format(value)));
            let extra = '';
            if (!Array.isArray(item)) {
                if (format === 'rating') extra = ' out of 5';
                else if (format === 'number') extra = total > 0 ? ' · ' + pct(value, total, 1) + '% of total' : '';
            }
            const tip = label + '|' + shown.replace(/<[^>]*>/g, '') + extra + (links[label] ? '|Click to filter' : '');
            html += '<li data-tip="' + e(tip) + '"' + (links[label] ? ' class="is-link" data-href="' + e(links[label]) + '" tabindex="0" role="link"' : '') + '>'
                + '<span class="bars-label">' + e(label) + '</span>'
                + '<span class="bars-track"><span class="bars-fill" style="--w: ' + (max > 0 ? round(value / max * 100, 1) : 0) + '%"></span></span>'
                + '<span class="bars-num">' + shown + '</span></li>';
        }
        return html + '</ul>';
    }

    /** Donut chart. segments = [[label, value, cssColor, optional link]...]. */
    function donut_chart(segments, center, sub, size = 132) {
        const total = sum(segments.map(s => s[1])) || 1;
        let offset = 0, circles = '', legend = '';
        segments.forEach((seg, i) => {
            const [label, value, color] = seg;
            const len = value / total * 100;
            circles += '<circle cx="21" cy="21" r="15.915" fill="none" stroke="' + e(color) + '" stroke-width="6" stroke-dasharray="'
                + round(len, 3) + ' ' + round(100 - len, 3) + '" stroke-dashoffset="' + round(25 - offset, 3) + '" data-i="' + i + '" data-label="' + e(label)
                + '" data-value="' + Number(value) + '"' + (seg[3] ? ' data-href="' + e(seg[3]) + '"' : '') + '/>';
            offset += len;
            legend += '<li data-i="' + i + '" role="button" tabindex="0" title="Click to hide or show"><span class="swatch" style="background: ' + e(color) + '"></span>' + e(label) + '<strong>' + number_format(value) + '</strong></li>';
        });
        return '<div class="donut-wrap" data-donut><div class="donut" style="width:' + size + 'px;height:' + size + 'px">'
            + '<svg viewBox="0 0 42 42" role="img" aria-label="' + e(center + ' ' + sub) + '">' + circles + '</svg>'
            + '<div class="donut-center"><strong data-default="' + e(center) + '">' + e(center) + '</strong><span data-default="' + e(sub) + '">' + e(sub) + '</span></div></div>'
            + '<ul class="legend">' + legend + '</ul></div>';
    }

    let chartId = 0;
    /** Small area line chart. series = [[label, value]...]. */
    function line_chart(series, color = 'var(--red-500)', height = 140) {
        if (!series.length) return '<p class="empty">No trend data available.</p>';
        const vals = series.map(s => s[1]), labels = series.map(s => s[0]);
        const lo = Math.min(...vals) - (Math.max(...vals) - Math.min(...vals)) * 0.6 - 1;
        const hi = Math.max(...vals) + (Math.max(...vals) - Math.min(...vals)) * 0.3 + 1;
        const w = 300, h = 140, px = 12, py = 12;
        const pts = vals.map((v, i) => [round(px + i * ((w - 2 * px) / Math.max(1, vals.length - 1)), 1), round(py + (hi - v) / (hi - lo) * (h - 2 * py), 1), v]);
        const line = 'M' + pts.map(p => p[0] + ' ' + p[1]).join(' L');
        const last = pts[pts.length - 1];
        const area = line + ' L' + last[0] + ' ' + h + ' L' + pts[0][0] + ' ' + h + ' Z';
        const id = 'g' + (++chartId);
        let grid = '';
        for (let g = 1; g <= 3; g++) grid += '<line x1="0" x2="' + w + '" y1="' + (g * h / 4) + '" y2="' + (g * h / 4) + '" class="grid-line"/>';
        let dots = '';
        pts.forEach((p, i) => {
            const change = i ? p[2] - pts[i - 1][2] : null;
            dots += '<span class="line-dot" style="left:' + round(p[0] / w * 100, 2) + '%;top:' + round(p[1] / h * 100, 2) + '%;border-color:' + e(color) + '" data-x="' + round(p[0] / w * 100, 2)
                + '" data-label="' + e(labels[i]) + '" data-value="' + e(number_format(p[2])) + '" data-change="' + (change === null ? '' : (change > 0 ? '+' : '') + change + ' vs ' + e(labels[i - 1])) + '"></span>';
        });
        return '<div class="line-chart" data-line style="height:' + height + 'px"><span class="line-guide" hidden></span><svg viewBox="0 0 ' + w + ' ' + h + '" preserveAspectRatio="none" role="img" aria-label="Trend from '
            + e(labels[0]) + ' to ' + e(labels[labels.length - 1]) + '"><defs><linearGradient id="' + id + '" x1="0" x2="0" y1="0" y2="1">'
            + '<stop offset="0%" stop-color="' + e(color) + '" stop-opacity=".22"/><stop offset="100%" stop-color="' + e(color) + '" stop-opacity="0"/></linearGradient></defs>'
            + grid + '<path d="' + area + '" fill="url(#' + id + ')"/><path d="' + line + '" fill="none" stroke="' + e(color) + '" stroke-width="2" vector-effect="non-scaling-stroke"/></svg>'
            + dots + '</div><div class="line-labels">' + labels.map(l => '<span>' + e(l) + '</span>').join('') + '</div>';
    }

    function stat_card(label, value, note, iconName, tone, href = null) {
        const t = href ? 'a' : 'div';
        return '<' + t + ' class="card stat"' + (href ? ' href="' + e(href) + '"' : '') + '>'
            + '<span class="stat-icon tone-' + e(tone) + '">' + icon(iconName, 20) + '</span><div>'
            + '<div class="stat-label">' + e(label) + '</div><div class="stat-value">' + e(value) + '</div>'
            + '<div class="stat-note">' + note + '</div></div></' + t + '>';
    }

    Object.assign(P, { initials, select_filter, chip_links, status_tone, tag, avatar, rel_time, days_ago, stars, bar_list, donut_chart, line_chart, stat_card, optPairs });

    /* ================================================================== */
    /* Page shell                                                          */
    /* ================================================================== */

    function nav_items() {
        const forReview = can('academic.view') ? count_for_review() : 0;
        const items = [
            ['overview', 'Overview', 'grid', 'index.html', 0],
            ['incoming', 'Incoming enrollment', 'user-plus', 'incoming.html', forReview],
            ['schedule', 'Campus schedule', 'calendar', 'schedule.html', 0],
            ['enrollment', 'Enrollment', 'users', 'enrollment.html', 0],
            ['grades', 'Grade submissions', 'check-sq', 'grades.html', 0],
            ['risk', 'At-risk students', 'alert', 'at-risk.html', can('academic.view') ? at_risk().length : 0],
            ['evaluations', 'Faculty evaluations', 'chart', 'evaluations.html', 0],
            ['predictions', 'Outcome prediction', 'chart', 'predictions.html', 0],
            ['setup', 'Reference setup', 'grid', 'setup.html', 0],
        ];
        const need = { predictions: 'prediction.demo', setup: 'academic.manage', evaluations: 'sete.view' };
        return items.filter(([key]) => can(need[key] || 'academic.view'));
    }

    /** Things that need attention, for the bell menu. */
    function notifications() {
        if (!can('academic.view')) return [];
        const risk = at_risk();
        const urgent = risk.filter(r => r.priority === 'Urgent' && r.intervention === 'Not contacted').length;
        const pending = classes().filter(c => c.grade !== 'Submitted').length;
        const review = count_for_review();
        const conflicts = room_conflicts().length;
        return [
            [urgent, 'alert', 'red', urgent + ' urgent at-risk student' + (urgent === 1 ? '' : 's') + ' not yet contacted', 'at-risk.html?priority=Urgent&intervention=Not+contacted#list'],
            [pending, 'check-sq', 'orange', pending + ' class' + (pending === 1 ? '' : 'es') + ' still need final grades', 'grades.html?status=pending#list'],
            [review, 'user-plus', 'blue', review + ' applicant' + (review === 1 ? '' : 's') + ' waiting for review', 'incoming.html?status=For+review#list'],
            [conflicts, 'building', 'purple', conflicts + ' room conflict' + (conflicts === 1 ? '' : 's') + ' flagged', 'schedule.html#conflicts'],
        ].filter(i => i[0] > 0);
    }

    function page_start(active, title, user) {
        document.title = title + ' · ' + APP_NAME + ' ' + APP_TAGLINE;
        const notes = notifications();
        const flashMsg = take_flash();
        const ini = initials(user.name);
        const searchValue = PAGE_FILE === 'search.html' ? q('q') : '';
        let html = '<div class="app"><aside class="sidebar" id="sidebar"><div class="sidebar-top">'
            + '<a class="brand" href="index.html"><div class="brand-mark" role="img" aria-label="' + e(APP_NAME) + ' logo"></div><div>'
            + '<div class="brand-name">' + e(APP_NAME) + '</div><div class="brand-sub">' + e(APP_TAGLINE) + '</div></div></a>'
            + '<button class="icon-btn sidebar-close" id="sidebarClose" aria-label="Close menu">' + icon('x') + '</button></div>'
            + '<div class="nav-label">Workspace</div><nav class="nav">';
        for (const [key, label, ic, href, badge] of nav_items()) {
            html += '<a href="' + e(href) + '" class="nav-item' + (key === active ? ' is-active' : '') + '"' + (key === active ? ' aria-current="page"' : '') + '>'
                + icon(ic) + '<span>' + e(label) + '</span>' + (badge ? '<span class="badge badge--red">' + badge + '</span>' : '') + '</a>';
        }
        html += '</nav><div class="access-card"><div class="access-title">' + icon('shield', 14) + ' Static demo</div>'
            + '<p>Sample records only. Your changes are saved in this browser and nobody else sees them.</p></div>'
            + '<div class="sidebar-user"><span class="avatar avatar--gradient">' + e(ini) + '</span><div class="sidebar-user-text">'
            + '<strong>' + e(user.name) + '</strong><span>' + e(user.role) + '</span></div>'
            + '<button class="icon-btn" data-menu-toggle="userMenu" aria-label="Account menu">' + icon('more', 18) + '</button></div></aside>'
            + '<div class="scrim" id="scrim"></div><div class="main"><header class="topbar">'
            + '<button class="icon-btn menu-btn" id="menuBtn" aria-label="Open menu">' + icon('menu') + '</button>'
            + '<form class="search" action="search.html" method="get" role="search">' + icon('search', 18)
            + '<input type="search" name="q" id="globalSearch" value="' + e(searchValue) + '" placeholder="' + (can('academic.view') ? 'Search school-wide records' : 'Demonstration access only') + '"' + (can('academic.view') ? '' : ' disabled') + ' autocomplete="off">'
            + '<kbd>Ctrl K</kbd></form><div class="topbar-actions"><div class="menu-wrap">'
            + '<button class="icon-btn bell" data-menu-toggle="noteMenu" aria-label="Notifications (' + notes.length + ')">' + icon('bell') + (notes.length ? '<span class="dot"></span>' : '') + '</button>'
            + '<div class="menu menu--wide" id="noteMenu" hidden><div class="menu-head"><strong>Notifications</strong><span>'
            + (notes.length ? notes.length + ' item' + (notes.length === 1 ? '' : 's') + ' need attention' : 'You’re all caught up') + '</span></div>';
        for (const [, ic, tone, text, href] of notes) {
            html += '<a class="menu-item menu-note" href="' + e(href) + '"><span class="stat-icon tone-' + tone + '">' + icon(ic, 16) + '</span><span>' + e(text) + '</span>' + icon('chev-r', 14) + '</a>';
        }
        if (!notes.length) html += '<p class="empty">Nothing needs attention right now.</p>';
        html += '</div></div><div class="menu-wrap">'
            + '<button class="avatar avatar--gradient avatar--btn" data-menu-toggle="userMenu" aria-label="Account menu">' + e(ini) + '</button>'
            + '<div class="menu" id="userMenu" hidden><div class="menu-head"><strong>' + e(user.name) + '</strong><span>' + e(user.email) + '</span></div>'
            + '<button type="button" class="menu-item" data-reset-demo>' + icon('refresh', 16) + ' Reset demo data</button>'
            + '</div></div></div></header><main class="content">';

        if (can('academic.view')) {
            const terms = all_terms();
            if (terms.length) {
                const years = Array.from(new Set(terms.map(academic_year)));
                const ay = academic_year(current_term());
                html += '<form method="get" class="filters academic-context" aria-label="Academic period filters">';
                for (const [k, v] of Object.entries(currentParams())) if (!['ay', 'term', 'export'].includes(k)) html += '<input type="hidden" name="' + e(k) + '" value="' + e(v) + '">';
                html += '<label>Academic year <select name="ay" class="f-input" aria-label="Academic year" data-autosubmit>'
                    + years.map(y => '<option' + (y === ay ? ' selected' : '') + '>' + e(y) + '</option>').join('') + '</select></label>'
                    + '<label>Semester <select name="term" class="f-input" aria-label="Semester" data-autosubmit>'
                    + terms.filter(t => academic_year(t) === ay).map(t => '<option value="' + t.id + '"' + (t.id === current_term().id ? ' selected' : '') + '>' + e(t.name) + '</option>').join('')
                    + '</select></label>'
                    + (is_historical_term() ? '<span class="pill pill--grey">Historical term · read-only · current profile statuses</span>' : '')
                    + '</form>';
            }
        }
        if (can('academic.view') && setup_missing().length) {
            html += '<section class="card panel" role="alert" aria-label="Academic setup required"><h2>Academic setup required</h2>'
                + '<p class="muted">Academic setup is incomplete. Empty counts represent missing records, not a completed academic assessment. Configure ' + e(setup_missing().join(', ')) + ' before entering academic records. '
                + (can('academic.manage') ? '<a href="setup.html">Open reference setup</a>' : 'Contact an administrator.') + '</p></section>';
        }
        if (flashMsg) {
            html += '<div class="toast toast--' + e(flashMsg.type) + '" id="toast" role="status">' + icon(flashMsg.type === 'ok' ? 'check' : 'alert', 18)
                + '<span>' + e(flashMsg.text) + '</span><button type="button" class="icon-btn" data-toast-close aria-label="Dismiss">' + icon('x', 16) + '</button></div>';
        }
        return html;
    }

    /** Title block for detail pages, with a "back to overview" crumb. */
    function page_head(title, subtitle, actions = '') {
        return '<section class="page-head page-head--detail"><div><a class="crumb" href="index.html">' + icon('chev-l', 14) + ' Overview</a>'
            + '<h1>' + e(title) + '</h1><p class="muted">' + subtitle + '</p></div>'
            + (actions ? '<div class="filters">' + actions + '</div>' : '') + '</section>';
    }

    const export_button = () => '<a class="btn btn--ghost btn--sm" href="' + e(url_with({ export: 'csv' })) + '">' + icon('download', 16) + ' Export CSV</a>';

    function page_end() {
        return '<footer class="foot muted xsmall">© ' + date('Y', app_now()) + ' ' + e(APP_NAME) + ' · Faculty Portal · ' + e(current_term().name) + ' · Static demo</footer>'
            + '</main></div></div>'
            + '<button class="help-fab" data-modal="helpModal" aria-label="Help" title="Help">' + icon('help', 22) + '</button>'
            + '<dialog class="modal" id="helpModal"><div class="modal-card"><header class="modal-head"><div><h2>Help</h2><p class="muted small">How to get around the faculty portal</p></div>'
            + '<button type="button" class="icon-btn" data-close aria-label="Close">' + icon('x', 18) + '</button></header><div class="modal-body"><ul class="help-list">'
            + '<li><span class="stat-icon tone-blue">' + icon('search', 16) + '</span><div><strong>Search anything</strong><p>Press <kbd>Ctrl</kbd> + <kbd>K</kbd>, then type a name, student number, applicant ID or class code.</p></div></li>'
            + '<li><span class="stat-icon tone-green">' + icon('plus', 16) + '</span><div><strong>Add records</strong><p>Use the blue “Add” button at the top of Incoming enrollment, Campus schedule, Enrollment and Faculty evaluations.</p></div></li>'
            + '<li><span class="stat-icon tone-purple">' + icon('edit', 16) + '</span><div><strong>Edit or delete</strong><p>Every table row ends with a pencil (edit) and a bin (delete). Deleting always asks first.</p></div></li>'
            + '<li><span class="stat-icon tone-orange">' + icon('check-sq', 16) + '</span><div><strong>Encode grades</strong><p>Open Grade submissions and click a class to edit its roster, save a draft or submit final grades.</p></div></li>'
            + '<li><span class="stat-icon tone-red">' + icon('alert', 16) + '</span><div><strong>Log interventions</strong><p>On At-risk students, use the pencil on a row to record contact, counseling and notes.</p></div></li>'
            + '<li><span class="stat-icon tone-blue">' + icon('chart', 16) + '</span><div><strong>Explore charts</strong><p>Hover any chart for exact figures. Click bars, donut slices and funnel steps to filter the list; click a legend entry to hide or show a series.</p></div></li>'
            + '<li><span class="stat-icon tone-green">' + icon('download', 16) + '</span><div><strong>Export</strong><p>“Export CSV” downloads the list exactly as currently filtered.</p></div></li>'
            + '<li><span class="stat-icon tone-slate">' + icon('refresh', 16) + '</span><div><strong>Static demo</strong><p>Changes are stored only in this browser. Use “Reset demo data” in the account menu to start over.</p></div></li>'
            + '</ul></div><footer class="modal-foot"><button type="button" class="btn btn--primary btn--sm" data-close>Got it</button></footer></div></dialog>'
            + '<dialog class="modal modal--sm" id="confirmModal"><form method="post" class="modal-card" data-action-form>'
            + '<input type="hidden" name="action" value=""><input type="hidden" name="id" value=""><input type="hidden" name="return" value="">'
            + '<div id="confirmExtra"></div><div class="modal-body confirm-body"><span class="stat-icon tone-red">' + icon('trash', 20) + '</span>'
            + '<div><h2>Are you sure?</h2><p class="muted" id="confirmText"></p></div></div><footer class="modal-foot">'
            + '<button type="button" class="btn btn--ghost btn--sm" data-close>Cancel</button>'
            + '<button type="submit" class="btn btn--danger btn--sm">' + icon('trash', 16) + ' <span id="confirmLabel">Delete</span></button></footer></form></dialog>'
            + '<div class="tip" id="tip" hidden></div>';
    }

    Object.assign(P, { page_start, page_head, page_end, export_button });

    /* ================================================================== */
    /* Pop-up forms                                                        */
    /* ================================================================== */

    function attrs(a) {
        let html = '';
        for (const k of Object.keys(a)) {
            const v = a[k];
            if (v === false || v === null || v === undefined) continue;
            html += ' ' + e(k) + (v === true ? '' : '="' + e(v) + '"');
        }
        return html;
    }
    const field = (label, control, hint = '', span = 1) => '<label class="f f--' + span + '"><span class="f-label">' + e(label) + '</span>' + control
        + (hint !== '' ? '<small>' + e(hint) + '</small>' : '') + '</label>';
    const input = (name, a = {}) => '<input class="f-input"' + attrs(Object.assign({ type: 'text', name }, a)) + '>';

    /** options: list of strings, [[value, label]...], a plain object, or { grouped: [[group, pairs]...] }. */
    function select_input(name, options, a = {}, blank = null) {
        const grouped = options && options.grouped;
        const empty = grouped ? !options.grouped.length : !optPairs(options).length;
        a = Object.assign({}, a);
        if (empty) { a.disabled = true; blank = 'Setup required: no options available'; }
        const render = opts => optPairs(opts).map(([v, l]) => '<option value="' + e(v) + '">' + e(l) + '</option>').join('');
        let html = '<select class="f-input"' + attrs(Object.assign({}, a, { name })) + '>';
        if (blank !== null) html += '<option value="">' + e(blank) + '</option>';
        if (grouped) for (const [g, opts] of options.grouped) html += '<optgroup label="' + e(g) + '">' + render(opts) + '</optgroup>';
        else html += render(options);
        return html + '</select>';
    }

    function modal_start(id, title, action, sub = '', cls = '') {
        return '<dialog class="modal' + (cls ? ' ' + e(cls) : '') + '" id="' + e(id) + '">'
            + '<form method="post" class="modal-card" data-action-form>'
            + '<input type="hidden" name="action" value="' + e(action) + '">'
            + '<input type="hidden" name="return" value="' + e(return_url()) + '">'
            + '<input type="hidden" name="id" value="">'
            + '<header class="modal-head"><div><h2 data-modal-title data-default="' + e(title) + '">' + e(title) + '</h2>'
            + (sub !== '' ? '<p class="muted small">' + e(sub) + '</p>' : '') + '</div>'
            + '<button type="button" class="icon-btn" data-close aria-label="Close">' + icon('x', 18) + '</button></header><div class="modal-body">';
    }
    const modal_end = (submit = 'Save') => '</div><footer class="modal-foot"><button type="button" class="btn btn--ghost btn--sm" data-close>Cancel</button>'
        + '<button type="submit" class="btn btn--primary btn--sm">' + icon('check', 16) + ' ' + e(submit) + '</button></footer></form></dialog>';

    /** Button that opens a modal. With fill it opens in "edit" mode with those values. */
    function modal_button(modal, label, cls = 'btn btn--primary btn--sm', fill = null, title = '', aria = '') {
        const action = { applicantModal: 'applicant_save', studentModal: 'student_save', facultyModal: 'faculty_save', classModal: 'section_save', interventionModal: 'intervention_save' }[modal] || '';
        if (action !== '' && is_historical_term()) return '';
        if (action !== '' && !can(action_permission(action))) return '';
        if (action !== '' && setup_missing_for_action(action).length) return '<span class="muted small">Setup required before adding records</span>';
        return '<button type="button" class="' + e(cls) + '" data-modal="' + e(modal) + '"'
            + (fill !== null ? ' data-fill="' + e(JSON.stringify(fill)) + '"' : '')
            + (title !== '' ? ' data-title="' + e(title) + '"' : '')
            + (aria !== '' ? ' aria-label="' + e(aria) + '" title="' + e(aria) + '"' : '') + '>' + label + '</button>';
    }

    /** Button that asks "are you sure?" and then runs a delete. */
    function confirm_button(action, id, text, label = '', cls = 'icon-btn icon-btn--danger', extra = {}, ret = '', aria = 'Delete') {
        if (is_historical_term()) return '';
        if (!can(action_permission(action))) return '';
        return '<button type="button" class="' + e(cls) + '" data-confirm="' + e(action) + '" data-id="' + e(id) + '" data-text="' + e(text) + '"'
            + (Object.keys(extra).length ? ' data-extra="' + e(JSON.stringify(extra)) + '"' : '')
            + ' data-return="' + e(ret !== '' ? ret : return_url()) + '" aria-label="' + e(aria) + '" title="' + e(aria) + '">'
            + (label !== '' ? label : icon('trash', 16)) + '</button>';
    }

    const row_actions = (modal, fill, editTitle, deleteAction, id, confirmText) => '<div class="row-actions">'
        + modal_button(modal, icon('edit', 16), 'icon-btn', fill, editTitle, 'Edit') + confirm_button(deleteAction, id, confirmText) + '</div>';

    const YEAR_OPTIONS = [['1', '1st year'], ['2', '2nd year'], ['3', '3rd year'], ['4', '4th year']];
    const BLOCKS = ['A', 'B', 'C', 'D', 'E'];
    const RANKS = ['Instructor', 'Lecturer', 'Assistant Professor', 'Associate Professor', 'Professor'];

    function applicant_modal() {
        const reqs = REQUIREMENTS.map((r, i) => '<label class="check"><input type="checkbox" name="req[]" value="' + i + '"><span>' + e(r) + '</span></label>').join('');
        return modal_start('applicantModal', 'Add applicant', 'applicant_save', intake_label() + ' admissions')
            + '<div class="f-grid">'
            + field('First name', input('first_name', { required: true, maxlength: 60 }))
            + field('Middle name', input('middle_name', { maxlength: 60 }))
            + field('Last name', input('last_name', { required: true, maxlength: 60 }))
            + field('Sex', select_input('sex', [['F', 'Female'], ['M', 'Male']]))
            + field('Email', input('email', { type: 'email', required: true, maxlength: 120 }), '', 2)
            + field('Program applied for', select_input('program', program_choices(), { required: true }), '', 2)
            + field('Applicant type', select_input('type', ['Freshman', 'Transferee']))
            + field('SHS strand', select_input('shs_strand', ['STEM', 'ABM', 'HUMSS', 'GAS', 'TVL'], {}, 'None (transferee)'))
            + field('SHS average / GWA', input('prior_gwa', { type: 'number', step: '0.01', min: 1, max: 100 }), '75–100 for SHS, 1.00–5.00 for college GWA')
            + field('Entrance exam score', input('exam_score', { type: 'number', min: 0, max: 100 }), 'Leave blank if not yet examined')
            + field('Status', select_input('status', ['For review', 'Qualified', 'Pending documents', 'Enrolled', 'Not qualified']), '', 3)
            + '<fieldset class="f f--3"><legend class="f-label">Requirements submitted</legend><div class="check-grid">' + reqs + '</div></fieldset>'
            + '</div>' + modal_end('Save applicant');
    }

    function student_modal() {
        return modal_start('studentModal', 'Add student', 'student_save', current_term().name)
            + '<div class="f-grid">'
            + field('First name', input('first_name', { required: true, maxlength: 60 }))
            + field('Middle name', input('middle_name', { maxlength: 60 }))
            + field('Last name', input('last_name', { required: true, maxlength: 60 }))
            + field('Sex', select_input('sex', [['F', 'Female'], ['M', 'Male']]))
            + field('Birthdate', input('birthdate', { type: 'date', required: true }))
            + field('Email', input('email', { type: 'email', maxlength: 120 }), 'Blank = school address is created')
            + field('Program', select_input('program', program_choices(), { required: true }), '', 3)
            + field('Year level', select_input('year', YEAR_OPTIONS))
            + field('Block', select_input('block', BLOCKS))
            + field('Status', select_input('status', ['Regular', 'Irregular', 'On leave']))
            + field('Back subjects', input('back_subjects', { type: 'number', min: 0, max: 20, value: 0 }), RISK_BACK_SUBJECTS + ' or more on an irregular student counts as behind curriculum')
            + '</div>'
            + '<p class="f-note">' + icon('alert', 14) + ' A new student is enrolled in every class of the chosen block. Moving a student to another block re-enrolls them there and clears this term’s grades.</p>'
            + modal_end('Save student');
    }

    function faculty_modal() {
        return modal_start('facultyModal', 'Add faculty member', 'faculty_save')
            + '<div class="f-grid">'
            + field('Title', select_input('title', ['Prof.', 'Dr.', 'Engr.']))
            + field('First name', input('first_name', { required: true, maxlength: 60 }))
            + field('Last name', input('last_name', { required: true, maxlength: 60 }))
            + field('College', select_input('college', college_names()))
            + field('Academic rank', select_input('rank', RANKS))
            + field('Employment', select_input('employment', ['Full-time', 'Part-time']))
            + field('Email', input('email', { type: 'email', maxlength: 120 }), 'Blank = school address is created', 3)
            + '</div>' + modal_end('Save faculty member');
    }

    function intervention_modal() {
        return modal_start('interventionModal', 'Log intervention', 'intervention_save', '', 'modal--sm')
            + '<div class="f-grid f-grid--1">'
            + '<div class="f-person"><strong data-fill-text="name"></strong><span data-fill-text="detail"></span></div>'
            + field('Intervention status', select_input('status', INTERVENTION_STATUSES))
            + field('Last contact', input('last_contact', { type: 'date', max: date('Y-m-d', app_now()) }), 'Not needed for “Not contacted”')
            + field('Notes', '<textarea class="f-input" name="notes" rows="3" maxlength="255" placeholder="What was discussed, next steps…"></textarea>')
            + '</div>' + modal_end('Save intervention');
    }

    function class_modal() {
        const days = WEEK_DAYS.map(([k, label]) => '<option value="' + k + '">' + e(label) + '</option>').join('');
        let rooms = '<option value="">Room to be assigned</option>';
        for (const [college, list] of room_choices()) {
            rooms += '<optgroup label="' + e(college) + '">' + list.map(([n, l]) => '<option value="' + e(n) + '">' + e(l) + '</option>').join('') + '</optgroup>';
        }
        const row = '<div class="meet-row">'
            + '<select class="f-input" name="meet_day[]" aria-label="Day">' + days + '</select>'
            + '<input class="f-input" type="time" name="meet_start[]" value="08:00" step="900" aria-label="Start time" required>'
            + '<input class="f-input" type="time" name="meet_end[]" value="09:00" step="900" aria-label="End time" required>'
            + '<select class="f-input" name="meet_room[]" aria-label="Room">' + rooms + '</select>'
            + '<button type="button" class="icon-btn icon-btn--danger" data-meet-remove aria-label="Remove meeting">' + icon('x', 16) + '</button></div>';
        return modal_start('classModal', 'Add class', 'section_save', current_term().name, 'modal--lg')
            + '<div class="f-grid">'
            + field('Course', select_input('course_id', course_choices(), { required: true, 'data-lock-on-edit': true }), '', 3)
            + field('Program', select_input('program', program_choices(), { required: true, 'data-lock-on-edit': true }))
            + field('Year level', select_input('year', YEAR_OPTIONS, { 'data-lock-on-edit': true }))
            + field('Block', select_input('block', BLOCKS, { 'data-lock-on-edit': true }))
            + field('Faculty', select_input('faculty_id', faculty_choices(), {}, 'To be assigned (TBA)'), '', 2)
            + field('Mode', select_input('mode', ['Onsite', 'Online', 'Off-campus'], { 'data-mode-select': true }))
            + '<div class="f f--3" data-show-mode="Online" hidden><span class="f-label">Online platform</span>' + select_input('platform', ['Google Meet', 'MS Teams']) + '</div>'
            + '<fieldset class="f f--3" data-hide-mode="Off-campus"><legend class="f-label">Weekly meetings</legend>'
            + '<div class="meet-rows" id="meetRows"></div><template id="meetRowTpl">' + row + '</template>'
            + '<button type="button" class="btn btn--ghost btn--xs" id="meetAdd">' + icon('plus', 14) + ' Add meeting</button></fieldset>'
            + '</div>'
            + '<p class="f-note" data-edit-only hidden>' + icon('alert', 14) + ' Course and block can’t be changed once students are enrolled. Delete the class and add a new one instead.</p>'
            + '<p class="f-note" data-add-only>' + icon('alert', 14) + ' Students of the chosen block are enrolled in the new class automatically.</p>'
            + modal_end('Save class');
    }

    /** Values for the class form's edit mode. */
    function class_fill(c) {
        const hhmm = m => pad2(Math.floor(m / 60)) + ':' + pad2(m % 60);
        return {
            id: c.id, course_id: c.course_id, program: c.program, year: c.year, block: c.block,
            faculty_id: c.faculty_id === null ? '' : c.faculty_id, mode: c.mode, platform: c.platform || 'Google Meet',
            meetings: c.meetings.map(m => ({ day: m.day, start: hhmm(m.start), end: hhmm(m.end), room: m.room || '' })),
        };
    }

    Object.assign(P, { field, input, select_input, modal_button, confirm_button, row_actions, applicant_modal, student_modal, faculty_modal, intervention_modal, class_modal, class_fill, YEAR_OPTIONS, RANKS });

    /* ================================================================== */
    /* Saving: the port of actions.php                                     */
    /* ================================================================== */

    class InputError extends Error {}
    let post = {};

    const inp = key => { const v = post[key]; return typeof v === 'string' ? v.trim() : ''; };
    function need(key, label, max = 120) {
        const v = inp(key);
        if (v === '') throw new InputError(label + ' is required.');
        if (Array.from(v).length > max) throw new InputError(label + ' is too long (max ' + max + ' characters).');
        return v;
    }
    function one_of(key, allowed, label) {
        const v = inp(key);
        if (!allowed.includes(v)) throw new InputError('Choose a valid ' + label + '.');
        return v;
    }
    function whole(key, min, max, label) {
        const v = inp(key);
        if (!/^\d+$/.test(v) || +v < min || +v > max) throw new InputError(label + ' must be a number from ' + min + ' to ' + max + '.');
        return +v;
    }
    function email_in(key, required) {
        const v = inp(key);
        if (v === '') {
            if (required) throw new InputError('Email is required.');
            return null;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) || v.length > 120) throw new InputError('Enter a valid email address.');
        return v.toLowerCase();
    }
    function date_in(key, label) {
        const v = inp(key);
        const ts = /^\d{4}-\d{2}-\d{2}$/.test(v) ? strtotime(v) : null;
        if (ts === null || date('Y-m-d', ts) !== v) throw new InputError('Enter a valid ' + label + '.');
        return v;
    }
    const cut = (s, n) => Array.from(s).slice(0, n).join('');
    function program_id() {
        const p = DB.find('programs', x => x.code === inp('program'));
        if (!p) throw new InputError('Choose a valid program.');
        return p.id;
    }
    const slug = text => String(text).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z]/g, '') || 'x';
    const taken = (t, col, value, exceptId) => !!DB.find(t, r => r[col] === value && r.id !== exceptId);

    /** Class section in the current term, or an error. */
    function section_row(id) {
        const x = DB.get('sections', id);
        if (!x || x.term_id !== current_term().id) throw new InputError('That class no longer exists.');
        const co = DB.get('courses', x.course_id) || {}, p = DB.get('programs', x.program_id) || {};
        return Object.assign({}, x, { code: co.code, program: p.code });
    }
    const section_label = row => row.code + ' · ' + row.program + ' ' + row.year_level + row.block;

    /** Puts a student into every class of their block this term. */
    function enroll_in_block(studentId, programId, year, block) {
        const term = current_term().id;
        for (const x of DB.all('sections')) {
            if (x.term_id === term && x.program_id === programId && x.year_level === year && x.block === block && !DB.get('enrollments', x.id, studentId)) {
                DB.put('enrollments', { section_id: x.id, student_id: studentId, midterm_grade: null, final_grade: null });
            }
        }
    }
    const termSectionIds = () => new Set(DB.all('sections').filter(x => x.term_id === current_term().id).map(x => x.id));

    const ACTIONS = {
        applicant_save() {
            const id = parseInt(inp('id'), 10) || 0;
            const prior = inp('prior_gwa'), score = inp('exam_score');
            if (prior !== '' && (isNaN(Number(prior)) || +prior < 1 || +prior > 100)) throw new InputError('SHS average / GWA must be between 1 and 100.');
            const data = {
                first_name: need('first_name', 'First name', 60),
                middle_name: inp('middle_name') !== '' ? cut(inp('middle_name'), 60) : null,
                last_name: need('last_name', 'Last name', 60),
                sex: one_of('sex', ['F', 'M'], 'sex'),
                email: email_in('email', true),
                program_id: program_id(),
                type: one_of('type', ['Freshman', 'Transferee'], 'applicant type'),
                shs_strand: inp('shs_strand') !== '' ? one_of('shs_strand', ['STEM', 'ABM', 'HUMSS', 'GAS', 'TVL'], 'SHS strand') : null,
                prior_gwa: prior !== '' ? round(+prior, 2) : null,
                exam_score: score !== '' ? whole('exam_score', 0, 100, 'Exam score') : null,
                status: one_of('status', ['For review', 'Qualified', 'Pending documents', 'Enrolled', 'Not qualified'], 'status'),
                updated_at: now_sql(),
            };
            const name = data.first_name + ' ' + data.last_name;
            let row;
            if (id) {
                const old = DB.get('applicants', id);
                if (!old) throw new InputError('That applicant no longer exists.');
                row = DB.put('applicants', Object.assign({}, old, data));
            } else {
                let next = 0;
                for (const a of DB.all('applicants')) next = Math.max(next, parseInt(String(a.applicant_no).split('-').pop(), 10) || 0);
                row = DB.put('applicants', Object.assign({ id: DB.nextId('applicants'), exam_date: null }, data, {
                    applicant_no: 'APP-' + date('Y', app_now()) + '-' + String(next + 1).padStart(5, '0'),
                    submitted_at: now_sql(),
                }));
            }
            if (data.exam_score !== null && !row.exam_date) row = DB.put('applicants', Object.assign({}, row, { exam_date: date('Y-m-d', app_now()) }));
            const have = (Array.isArray(post['req']) ? post['req'] : []).map(v => parseInt(v, 10));
            REQUIREMENTS.forEach((requirement, i) => {
                const given = have.includes(i);
                const old = DB.get('applicant_requirements', row.id, requirement);
                const submitted_at = given ? ((old && old.submitted_at) || now_sql()) : null;
                DB.put('applicant_requirements', { applicant_id: row.id, requirement, submitted_at });
            });
            return id ? 'Applicant ' + name + ' updated.' : 'Applicant ' + name + ' added as ' + row.applicant_no + '.';
        },

        applicant_delete() {
            const id = parseInt(inp('id'), 10) || 0;
            const row = DB.get('applicants', id);
            if (!row) throw new InputError('That applicant no longer exists.');
            DB.del('applicants', id);
            DB.delWhere('applicant_requirements', r => r.applicant_id === id);
            return 'Applicant ' + row.first_name + ' ' + row.last_name + ' (' + row.applicant_no + ') deleted.';
        },

        student_save() {
            const id = parseInt(inp('id'), 10) || 0;
            const data = {
                first_name: need('first_name', 'First name', 60),
                middle_name: inp('middle_name') !== '' ? cut(inp('middle_name'), 60) : null,
                last_name: need('last_name', 'Last name', 60),
                sex: one_of('sex', ['F', 'M'], 'sex'),
                birthdate: date_in('birthdate', 'birthdate'),
                email: email_in('email', false),
                program_id: program_id(),
                year_level: whole('year', 1, 4, 'Year level'),
                block: one_of('block', BLOCKS, 'block'),
                status: one_of('status', ['Regular', 'Irregular', 'On leave'], 'status'),
                back_subjects: whole('back_subjects', 0, 20, 'Back subjects'),
            };
            const name = data.first_name + ' ' + data.last_name;
            const peer = DB.find('students', s => s.program_id === data.program_id && s.year_level === data.year_level && s.block === data.block && s.adviser_id !== null);
            const adviser = peer ? peer.adviser_id : null;
            if (id) {
                const old = DB.get('students', id);
                if (!old) throw new InputError('That student no longer exists.');
                if (data.email === null) data.email = old.email;
                if (taken('students', 'email', data.email, id)) throw new InputError('That email address is already used by another record.');
                const moved = old.program_id !== data.program_id || old.year_level !== data.year_level || old.block !== data.block;
                if (moved) data.adviser_id = adviser;
                DB.put('students', Object.assign({}, old, data));
                if (moved) {
                    const ids = termSectionIds();
                    DB.delWhere('enrollments', en => en.student_id === id && ids.has(en.section_id));
                    if (data.status !== 'On leave') enroll_in_block(id, data.program_id, data.year_level, data.block);
                }
                return 'Student ' + name + ' updated.' + (moved ? ' Moved to the new block’s classes.' : '');
            }
            const year = date('Y', app_now());
            let next = 10000;
            for (const s of DB.all('students')) if (String(s.student_no).startsWith(year + '-')) next = Math.max(next, parseInt(s.student_no.split('-').pop(), 10) || 0);
            next++;
            if (data.email === null) data.email = slug(data.first_name) + '.' + slug(data.last_name) + next + '@student.veritas.edu.ph';
            if (taken('students', 'email', data.email, null)) throw new InputError('That email address is already used by another record.');
            const newId = DB.nextId('students');
            DB.put('students', Object.assign({ id: newId, student_no: year + '-' + next }, data, { adviser_id: adviser, enrolled_at: now_sql() }));
            if (data.status !== 'On leave') enroll_in_block(newId, data.program_id, data.year_level, data.block);
            const n = DB.count('enrollments', en => en.student_id === newId);
            return 'Student ' + name + ' added as ' + year + '-' + next + ' and enrolled in ' + n + ' class' + (n === 1 ? '' : 'es') + '.';
        },

        student_delete() {
            const id = parseInt(inp('id'), 10) || 0;
            const row = DB.get('students', id);
            if (!row) throw new InputError('That student no longer exists.');
            DB.del('students', id);
            DB.delWhere('enrollments', en => en.student_id === id);
            DB.del('interventions', id);
            return 'Student ' + row.first_name + ' ' + row.last_name + ' (' + row.student_no + ') deleted, along with their class enrollments.';
        },

        faculty_save() {
            const id = parseInt(inp('id'), 10) || 0;
            const college = DB.find('colleges', c => c.short_name === inp('college'));
            if (!college) throw new InputError('Choose a valid college.');
            const data = {
                title: one_of('title', ['Prof.', 'Dr.', 'Engr.'], 'title'),
                first_name: need('first_name', 'First name', 60),
                last_name: need('last_name', 'Last name', 60),
                college_id: college.id,
                rank: one_of('rank', RANKS, 'academic rank'),
                employment: one_of('employment', ['Full-time', 'Part-time'], 'employment type'),
                email: email_in('email', false),
            };
            const name = data.title + ' ' + data.first_name + ' ' + data.last_name;
            if (id) {
                const old = DB.get('faculty', id);
                if (!old) throw new InputError('That faculty member no longer exists.');
                if (data.email === null) data.email = old.email;
                if (taken('faculty', 'email', data.email, id)) throw new InputError('That email address is already used by another record.');
                DB.put('faculty', Object.assign({}, old, data));
                return name + ' updated.';
            }
            const next = DB.nextId('faculty');
            if (data.email === null) data.email = slug(data.first_name).slice(0, 1) + slug(data.last_name) + next + '@veritas.edu.ph';
            if (taken('faculty', 'email', data.email, null)) throw new InputError('That email address is already used by another record.');
            DB.put('faculty', Object.assign({ id: next }, data));
            return name + ' added to the faculty list.';
        },

        faculty_delete() {
            const id = parseInt(inp('id'), 10) || 0;
            const row = DB.get('faculty', id);
            if (!row) throw new InputError('That faculty member no longer exists.');
            let n = 0;
            for (const x of DB.all('sections')) if (x.faculty_id === id) { DB.put('sections', Object.assign({}, x, { faculty_id: null })); n++; }
            for (const s of DB.all('students')) if (s.adviser_id === id) DB.put('students', Object.assign({}, s, { adviser_id: null }));
            DB.delWhere('faculty_rating_history', h => h.faculty_id === id);
            DB.del('faculty', id);
            return row.title + ' ' + row.first_name + ' ' + row.last_name + ' deleted.' + (n ? ' ' + n + ' class' + (n === 1 ? ' is' : 'es are') + ' now without a teacher (TBA).' : '');
        },

        section_save() {
            const id = parseInt(inp('id'), 10) || 0;
            const facultyId = inp('faculty_id') !== '' ? (parseInt(inp('faculty_id'), 10) || 0) : null;
            if (facultyId && !DB.get('faculty', facultyId)) throw new InputError('Choose a valid faculty member.');
            const mode = one_of('mode', ['Onsite', 'Online', 'Off-campus'], 'mode');
            const platform = mode === 'Online' ? one_of('platform', ['Google Meet', 'MS Teams'], 'online platform') : null;

            const meetings = [];
            if (mode !== 'Off-campus') {
                const list = k => (Array.isArray(post[k]) ? post[k] : []);
                const days = list('meet_day'), starts = list('meet_start'), ends = list('meet_end'), rooms = list('meet_room');
                const hhmm = /^([01]\d|2[0-3]):[0-5]\d$/;
                days.forEach((day, i) => {
                    const start = String(starts[i] || '').slice(0, 5), end = String(ends[i] || '').slice(0, 5);
                    if (!(day in DAY_ORDER) || !hhmm.test(start) || !hhmm.test(end)) throw new InputError('Each meeting needs a day, a start time and an end time.');
                    if (start >= end) throw new InputError('A meeting must end after it starts.');
                    let roomId = null;
                    const roomName = String(rooms[i] || '').trim();
                    if (mode === 'Onsite' && roomName !== '') {
                        const room = DB.find('rooms', r => r.name === roomName);
                        if (!room) throw new InputError('Room ' + roomName + ' does not exist.');
                        roomId = room.id;
                    }
                    meetings.push({ day, start_time: start + ':00', end_time: end + ':00', room_id: roomId });
                });
                if (meetings.length > 12) throw new InputError('A class can have at most 12 weekly meetings.');
            }

            let sectionId, message;
            if (id) {
                const row = section_row(id);
                const x = DB.get('sections', id);
                DB.put('sections', Object.assign({}, x, { faculty_id: facultyId || null, mode, platform }));
                sectionId = id;
                message = 'Class ' + section_label(row) + ' updated.';
            } else {
                const courseId = parseInt(inp('course_id'), 10) || 0;
                const course = DB.get('courses', courseId);
                if (!course) throw new InputError('Choose a valid course.');
                const programId = program_id();
                const year = whole('year', 1, 4, 'Year level');
                const block = one_of('block', BLOCKS, 'block');
                const term = current_term().id;
                if (DB.find('sections', x => x.term_id === term && x.course_id === courseId && x.program_id === programId && x.year_level === year && x.block === block)) {
                    throw new InputError(course.code + ' already has a class for ' + inp('program') + ' ' + year + block + ' this term.');
                }
                sectionId = DB.nextId('sections');
                DB.put('sections', { id: sectionId, term_id: term, course_id: courseId, program_id: programId, year_level: year, block, faculty_id: facultyId || null, mode, platform, grades_updated_at: null, grades_submitted_at: null });
                let n = 0;
                for (const s of DB.all('students')) {
                    if (s.program_id === programId && s.year_level === year && s.block === block && s.status !== 'On leave') {
                        DB.put('enrollments', { section_id: sectionId, student_id: s.id, midterm_grade: null, final_grade: null });
                        n++;
                    }
                }
                message = 'Class ' + course.code + ' · ' + inp('program') + ' ' + year + block + ' added with ' + n + ' student' + (n === 1 ? '' : 's') + ' enrolled.';
            }
            DB.delWhere('meetings', m => m.section_id === sectionId);
            for (const m of meetings) DB.put('meetings', Object.assign({ id: DB.nextId('meetings'), section_id: sectionId }, m));
            return message;
        },

        section_delete() {
            const row = section_row(parseInt(inp('id'), 10) || 0);
            DB.del('sections', row.id);
            DB.delWhere('meetings', m => m.section_id === row.id);
            DB.delWhere('enrollments', en => en.section_id === row.id);
            DB.del('evaluation_totals', row.id);
            return 'Class ' + section_label(row) + ' deleted, with its meetings, roster and grades.';
        },

        grades_save() {
            const row = section_row(parseInt(inp('id'), 10) || 0);
            const todo = one_of('do', ['save', 'submit', 'reopen'], 'action');
            const label = section_label(row);
            const x = () => DB.get('sections', row.id);
            if (todo === 'reopen') {
                DB.put('sections', Object.assign({}, x(), { grades_submitted_at: null }));
                return 'Grades for ' + label + ' reopened for editing.';
            }
            if (row.grades_submitted_at) throw new InputError('Grades for ' + label + ' are already submitted. Reopen them to make changes.');
            const mid = post.mid && typeof post.mid === 'object' ? post.mid : {};
            const fin = post.fin && typeof post.fin === 'object' ? post.fin : {};
            const clean = v => {
                v = typeof v === 'string' ? v.trim() : '';
                if (v !== '' && !GRADE_VALUES.includes(v)) throw new InputError('“' + v + '” is not a valid grade.');
                return v === '' ? null : v;
            };
            for (const en of DB.all('enrollments')) {
                if (en.section_id !== row.id) continue;
                const sid = String(en.student_id);
                if (sid in mid || sid in fin) DB.put('enrollments', Object.assign({}, en, { midterm_grade: clean(mid[sid] || ''), final_grade: clean(fin[sid] || '') }));
            }
            DB.put('sections', Object.assign({}, x(), { grades_updated_at: now_sql() }));
            DB.commit(); // the draft stays saved even if submitting fails below
            if (todo === 'submit') {
                const total = DB.count('enrollments', en => en.section_id === row.id);
                const missing = DB.count('enrollments', en => en.section_id === row.id && en.final_grade === null);
                if (!total) throw new InputError('This class has no students, so there are no grades to submit.');
                if (missing) throw new InputError('Draft saved, but not submitted: ' + missing + ' student' + (missing === 1 ? ' still has' : 's still have') + ' no final grade.');
                DB.put('sections', Object.assign({}, x(), { grades_submitted_at: now_sql() }));
                return 'Final grades for ' + label + ' submitted.';
            }
            return 'Grades for ' + label + ' saved as a draft.';
        },

        enrollment_add() {
            const row = section_row(parseInt(inp('id'), 10) || 0);
            const no = need('student_no', 'Student number', 12);
            const st = DB.find('students', s => s.student_no === no);
            if (!st) throw new InputError('No student has the number ' + no + '.');
            if (DB.get('enrollments', row.id, st.id)) throw new InputError(st.first_name + ' ' + st.last_name + ' is already in this class.');
            DB.put('enrollments', { section_id: row.id, student_id: st.id, midterm_grade: null, final_grade: null });
            return st.first_name + ' ' + st.last_name + ' added to ' + section_label(row) + '.';
        },

        enrollment_remove() {
            const row = section_row(parseInt(inp('id'), 10) || 0);
            const st = DB.get('students', parseInt(inp('student'), 10) || 0);
            if (!st || !DB.del('enrollments', row.id, st.id)) throw new InputError('That student is not in this class.');
            return st.first_name + ' ' + st.last_name + ' removed from ' + section_label(row) + '.';
        },

        intervention_save() {
            const id = parseInt(inp('id'), 10) || 0;
            const st = DB.get('students', id);
            if (!st) throw new InputError('That student no longer exists.');
            const name = st.first_name + ' ' + st.last_name;
            const status = one_of('status', INTERVENTION_STATUSES, 'intervention status');
            if (status === 'Not contacted') {
                DB.del('interventions', id);
                return 'Intervention record for ' + name + ' cleared.';
            }
            const today = date('Y-m-d', app_now());
            const day = inp('last_contact') !== '' ? date_in('last_contact', 'contact date') : today;
            if (day > today) throw new InputError('The contact date can’t be in the future.');
            DB.put('interventions', {
                student_id: id, status,
                last_contact_at: day === today ? now_sql() : day + ' 12:00:00',
                notes: inp('notes') !== '' ? cut(inp('notes'), 255) : null,
            });
            return 'Intervention for ' + name + ' saved: ' + status + '.';
        },

        intervention_delete() {
            if (!DB.del('interventions', parseInt(inp('id'), 10) || 0)) throw new InputError('There is no intervention record to delete.');
            return 'Intervention record deleted. The student is back to “Not contacted”.';
        },

        /** Minimal reference setup: real catalogs supplied by an authorized administrator. */
        catalog_save() {
            const kind = one_of('kind', ['college', 'program', 'course', 'room', 'term'], 'reference type');
            const name = need('name', 'Name', kind === 'term' || kind === 'room' ? 40 : 100);
            let collegeId = null;
            if (['program', 'course', 'room'].includes(kind)) {
                collegeId = whole('college_id', 1, 255, 'College');
                if (!DB.get('colleges', collegeId)) throw new InputError('Choose an existing college.');
            }
            const dup = () => { throw new InputError('That code or name is already used by another record.'); };
            if (kind === 'college') {
                const code = need('code', 'Code', 10), short = need('short_name', 'Short name', 40), building = need('building', 'Building', 80);
                if (taken('colleges', 'code', code, null) || taken('colleges', 'short_name', short, null)) dup();
                DB.put('colleges', { id: DB.nextId('colleges'), code, name, short_name: short, building });
            } else if (kind === 'program') {
                const code = need('code', 'Code', 20);
                if (taken('programs', 'code', code, null)) dup();
                DB.put('programs', { id: DB.nextId('programs'), college_id: collegeId, code, name });
            } else if (kind === 'course') {
                const code = need('code', 'Code', 20);
                const units = whole('units', 1, 20, 'Units');
                const courseKind = one_of('course_kind', ['Lecture', 'Lab', 'PE', 'NSTP', 'Practicum'], 'course kind');
                if (taken('courses', 'code', code, null)) dup();
                DB.put('courses', { id: DB.nextId('courses'), college_id: collegeId, code, title: name, units, kind: courseKind });
            } else if (kind === 'room') {
                const type = one_of('room_type', ['Lecture', 'Laboratory', 'Gym'], 'room type');
                const capacity = whole('capacity', 1, 1000, 'Capacity');
                if (taken('rooms', 'name', name, null)) dup();
                DB.put('rooms', { id: DB.nextId('rooms'), college_id: collegeId, name, type, capacity });
            } else {
                const starts = date_in('starts_on', 'start date'), ends = date_in('ends_on', 'end date'), deadline = date_in('grade_deadline', 'grade deadline');
                if (starts > ends || deadline < ends) throw new InputError('Term dates must be ordered; grade deadline must be on/after term end.');
                if (taken('terms', 'name', name, null)) dup();
                for (const t of DB.all('terms')) if (t.is_current) DB.put('terms', Object.assign({}, t, { is_current: 0 }));
                DB.put('terms', { id: DB.nextId('terms'), name, starts_on: starts, ends_on: ends, grade_deadline: deadline, is_current: 1 });
            }
            return kind[0].toUpperCase() + kind.slice(1) + ' reference saved.';
        },
    };

    /** Turns form fields into the shape PHP's $_POST would have (name[] lists, name[key] maps). */
    function form_post(form, submitter) {
        const out = {};
        const fd = new FormData(form);
        if (submitter && submitter.name) fd.append(submitter.name, submitter.value);
        for (const [k, v] of fd) {
            let m;
            if ((m = /^(.+)\[\]$/.exec(k))) (out[m[1]] = Array.isArray(out[m[1]]) ? out[m[1]] : []).push(v);
            else if ((m = /^(.+)\[([^\]]+)\]$/.exec(k))) { out[m[1]] = out[m[1]] && typeof out[m[1]] === 'object' && !Array.isArray(out[m[1]]) ? out[m[1]] : {}; out[m[1]][m[2]] = v; }
            else out[k] = v;
        }
        return out;
    }

    function back_to(to) {
        if (typeof to !== 'string' || !/^[a-z-]+\.html(\?[^\s#]*)?(#[\w-]*)?$/.test(to)) to = 'index.html';
        go(to);
    }

    /** Runs one form's action, leaves a message and returns to the page it came from. */
    function submit_action(form, submitter) {
        post = form_post(form, submitter);
        const action = inp('action');
        const user = current_user();
        if (!ACTIONS[action]) {
            flash('error', 'Unknown action.');
        } else if (!can(action_permission(action), user)) {
            flash('error', 'Access denied. Your account role does not authorize this feature.');
        } else {
            DB.begin();
            try {
                if (is_historical_term()) throw new InputError('Historical terms are read-only. Select the current term before changing records.');
                const missing = setup_missing_for_action(action);
                if (missing.length) throw new InputError('Setup required: ' + missing.join(', ') + '. Ask an administrator to configure the reference catalogs.');
                const message = ACTIONS[action]();
                DB.commit();
                flash('ok', message);
            } catch (err) {
                DB.rollback();
                if (!(err instanceof InputError)) console.error(err);
                flash('error', err instanceof InputError ? err.message : 'The change could not be saved. Please try again.');
            }
            if (!DB.save()) flash('error', 'This browser blocked local storage, so the change can’t be kept after you leave the page.');
        }
        back_to(inp('return'));
    }

    P.submit_action = submit_action;

    /* ================================================================== */
    /* Outcome prediction (decision tree exported from scikit-learn)       */
    /* ================================================================== */

    const model = () => (window.PORTAL_MODEL || {});

    function predict_outcome(input) {
        const tree = model().tree;
        if (!tree || tree.format_version !== 1 || tree.features.map(f => f.key).join() !== 'enrolled,approved,grade,evaluations' || tree.classes.join() !== 'Dropout,Enrolled,Graduate') {
            throw new Error('Unsupported model schema');
        }
        const values = tree.features.map(f => {
            const v = input[f.key];
            if (typeof v !== 'number' || !isFinite(v) || v < f.min || v > f.max || (f.integer && Math.floor(v) !== v)) {
                throw new InputError(f.label + ' must be ' + (f.integer ? 'a whole number' : 'a number') + ' between ' + f.min + ' and ' + f.max + '.');
            }
            return Math.fround(v); // scikit-learn compares float32 values
        });
        if (input.approved > input.enrolled) throw new InputError('Passed units cannot exceed enrolled units.');
        let node = 0;
        for (let steps = 0; steps < tree.children_left.length; steps++) {
            if (tree.children_left[node] === -1) break;
            node = values[tree.feature[node]] <= tree.threshold[node] ? tree.children_left[node] : tree.children_right[node];
        }
        if (tree.children_left[node] !== -1) throw new Error('Invalid model tree');
        const probs = tree.probabilities[node];
        const outcome = tree.classes[probs.indexOf(Math.max(...probs))];
        const advice = {
            Dropout: ['Priority academic support', 'Invite the student for a supportive advising conversation. Review passed units, learning needs, and available tutoring or assistance; confirm the context before acting.'],
            Enrolled: ['Monitor academic progress', 'Review course completion and arrange a progress check with an adviser. The Enrolled outcome does not establish that the student is academically safe or will drop out.'],
            Graduate: ['Routine academic support', 'Continue routine advising and monitor progress. A predicted Graduate outcome is not a guarantee of graduation.'],
        }[outcome];
        const probabilities = {};
        tree.classes.forEach((c, i) => { probabilities[c] = probs[i]; });
        return { outcome, indicator: advice[0], recommendation: advice[1], probabilities };
    }

    Object.assign(P, { model, predict_outcome, InputError });
})();
