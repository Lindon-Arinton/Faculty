/*
 * BSU Faculty Portal: static demo pages. Each function is the port of one
 * PHP page and returns the page's HTML; boot() picks one from <body data-page>.
 */
(function () {
    'use strict';

    const P = window.Portal;
    const {
        e, q, icon, url_with, number_format, pct, round, sum, count_values, cmp, cmpStr, date, strtotime, app_now, meta,
        tag, avatar, stat_card, bar_list, donut_chart, line_chart, stars, rel_time, days_ago, clock, status_tone,
        select_filter, chip_links, search_rows, filter_rows, paginate, pagination, maybe_export_csv,
        page_start, page_head, page_end, export_button, modal_button, confirm_button, row_actions,
        current_term, is_historical_term, intake_label, colleges, college_names, valid_college, year_label, section_year,
        can, REQUIREMENTS, RISK_TYPES, INTERVENTION_STATUSES, EVAL_LABELS, WEEK_DAYS, GRADE_VALUES,
    } = P;

    const qs = obj => P.buildQuery(obj);
    const first = list => (list.length ? list[0] : null);
    const last = list => (list.length ? list[list.length - 1] : null);
    /** arsort for [[key, value]...]: highest first, ties keep their order. */
    const bySizeDesc = pairs => pairs.slice().sort((a, b) => b[1] - a[1]);
    const countBy = (rows, key) => {
        const m = new Map();
        for (const r of rows) { const k = typeof key === 'function' ? key(r) : r[key]; m.set(k, (m.get(k) || 0) + 1); }
        return Array.from(m);
    };

    /* ================================================================== */
    /* Overview                                                            */
    /* ================================================================== */

    function prediction_panel() {
        if (!can('prediction.demo')) return '';
        try {
            const outcomes = { Dropout: 0, Enrolled: 0, Graduate: 0 };
            for (const s of P.model().samples) outcomes[P.predict_outcome(s.features).outcome]++;
            return '<section class="card panel" aria-label="Demonstration predictive analytics">'
                + '<div class="panel-head"><div><h2>Student outcome prediction</h2><p class="muted small">Demonstration · External UCI data · After the first semester</p></div><a class="link-strong" href="predictions.html">Explore predictions ' + icon('chev-r', 16) + '</a></div>'
                + '<div class="grid grid-3">'
                + Object.keys(outcomes).map(label => stat_card('Demo: predicted ' + label, String(outcomes[label]), 'Of 12 external sample records', 'chart', label === 'Dropout' ? 'orange' : 'blue', 'predictions.html')).join('')
                + '</div></section>';
        } catch (err) {
            return '<section class="card panel"><h2>Student outcome prediction</h2><p class="muted">Demonstration model unavailable. Academic records and SETE reports remain accessible.</p></section>';
        }
    }

    function overview() {
        const user = P.require_login();
        if (!can('academic.view', user)) { location.replace(P.home_for_user(user)); throw new P.Halt(); }

        const collegeFilter = valid_college(q('college'));
        const inScope = r => collegeFilter === '' || r.college === collegeFilter;
        const now = app_now();

        const studentsAll = P.students().filter(inScope);
        const applicantsAll = P.applicants().filter(inScope);
        const classesAll = P.classes().filter(inScope);
        const riskAll = P.at_risk().filter(inScope);
        const facultyScope = P.faculty().filter(inScope);
        const facultyAll = facultyScope.filter(f => f.responses > 0);
        const scopeColleges = collegeFilter ? [[collegeFilter, colleges().get(collegeFilter)]] : Array.from(colleges());
        const collegeQs = collegeFilter ? qs({ college: collegeFilter }) : '';
        const scoped = url => collegeFilter ? url + (url.includes('?') ? '&' : '?') + collegeQs : url;

        // Enrollment breakdown: by college, or by program inside one college
        const breakdownLabel = collegeFilter ? 'Enrollment by program' : 'Enrollment by college';
        const breakdown = bySizeDesc(countBy(studentsAll, s => (collegeFilter ? s.program : s.college)));
        const prevEnrollment = +meta('prev_term_enrollment', '0');
        const enrollmentGrowth = !collegeFilter && prevEnrollment ? round((studentsAll.length / prevEnrollment - 1) * 100, 1) : null;

        // Grade submission
        const gradeCounts = Object.assign({ Submitted: 0, 'In progress': 0, 'Not started': 0 }, count_values(classesAll.map(c => c.grade)));
        const gradeStatus = [
            ['Submitted', gradeCounts.Submitted, 'var(--blue-600)'],
            ['In progress', gradeCounts['In progress'], 'var(--orange-500)'],
            ['Not started', gradeCounts['Not started'], 'var(--slate-200)'],
        ];
        const gradesPending = gradeCounts['In progress'] + gradeCounts['Not started'];
        const deadline = strtotime(current_term().grade_deadline);
        const daysToDeadline = Math.round((strtotime(date('Y-m-d', deadline)) - strtotime(date('Y-m-d', now))) / 86400);

        // Grade progress rows: colleges with the most pending first, or year levels inside one college
        const groups = new Map();
        for (const c of classesAll) {
            const key = collegeFilter ? year_label(section_year(c.section)) : c.college;
            if (!groups.has(key)) groups.set(key, []);
            groups.get(key).push(c);
        }
        let groupList = Array.from(groups);
        if (collegeFilter) groupList.sort((a, b) => cmp(a[0], b[0]));
        let gradeByCollege = groupList.map(([label, list]) => ({
            college: label, link: 'grades.html?' + qs({ college: collegeFilter || label }), classes: list.length,
            pct: Math.round(sum(list.map(c => c.encoded)) / Math.max(1, list.length)),
            pending: list.filter(c => c.grade !== 'Submitted').length,
        }));
        if (!collegeFilter) gradeByCollege.sort((a, b) => b.pending - a.pending);
        gradeByCollege = gradeByCollege.slice(0, 3);

        const hasCurrentTerm = current_term().id > 0;
        const hasRiskEvidence = P.grade_evidence_count(collegeFilter) > 0 || riskAll.length > 0;
        const urgent = riskAll.filter(r => r.priority === 'Urgent').length;

        const stats = [
            ['Active classes', number_format(classesAll.length), !hasCurrentTerm ? 'No active term configured' : (collegeFilter ? 'Class sections this term' : 'Across ' + colleges().size + ' colleges'), 'calendar', 'blue', 'schedule.html' + (collegeQs ? '?' + collegeQs : '')],
            ['Enrolled students', number_format(studentsAll.length), !studentsAll.length ? 'No student records loaded' : (collegeFilter ? 'College enrollment' : 'School-wide enrollment'), 'users', 'purple', 'enrollment.html' + (collegeQs ? '?' + collegeQs : '')],
            ['Grades pending', classesAll.length ? number_format(gradesPending) : '—', classesAll.length ? 'Classes not submitted' : 'No class sections to assess', 'check-sq', 'orange', 'grades.html?status=pending' + (collegeQs ? '&' + collegeQs : '')],
            ['At-risk students', hasRiskEvidence ? number_format(riskAll.length) : '—', hasRiskEvidence ? urgent + ' need urgent attention' : 'No grade or backlog evidence', 'alert', 'red', 'at-risk.html' + (collegeQs ? '?' + collegeQs : '')],
        ];

        const riskTrend = P.risk_history(collegeFilter);

        // Admissions
        const appCounts = count_values(applicantsAll.map(a => a.status));
        const appTotal = applicantsAll.length;
        const forReview = appCounts['For review'] || 0;
        const enrolledNew = appCounts.Enrolled || 0;
        const qualified = enrolledNew + (appCounts.Qualified || 0) + (appCounts['Pending documents'] || 0);
        const examined = appTotal - forReview;
        const incomplete = applicantsAll.filter(a => a.missing.length && a.status === 'Pending documents').length;
        const prevIntake = +meta('prev_intake_applications', '0');
        const admissions = [
            ['Total applications', number_format(appTotal), collegeFilter ? pct(appTotal, P.applicants().length) + '% of all applications' : (prevIntake ? pct(appTotal - prevIntake, prevIntake, 1) + '% from last intake' : 'No prior intake baseline'), !collegeFilter && prevIntake > 0],
            ['For review', number_format(forReview), appTotal ? pct(forReview, appTotal) + '% awaiting exam results' : 'No applications recorded'],
            ['Qualified', number_format(qualified), examined ? pct(qualified, examined) + '% of examinees passed' : 'No examined applications'],
            ['Fully enrolled', number_format(enrolledNew), qualified ? pct(enrolledNew, qualified) + '% of qualified students' : 'No qualified applications'],
        ];
        const pipeline = [['Applications', appTotal, 'p1'], ['Examined', examined, 'p2'], ['Qualified', qualified, 'p3'], ['Enrolled', enrolledNew, 'p4']];
        const recentApplicants = applicantsAll.slice(0, 4);

        // Timetable for this weekday: one class at each of three times of day
        const todayKey = date('D', now) === 'Sun' ? 'Mon' : date('D', now);
        const daySessions = P.sessions_on(todayKey).filter(inScope);
        const schedule = [];
        for (const from of [480, 600, 780]) {
            const s = daySessions.find(x => x.start >= from && !schedule.includes(x));
            if (s) schedule.push(s);
        }
        const totalRooms = sum(scopeColleges.map(([, c]) => c.rooms.length));
        const conflictCount = P.room_conflicts().filter(x => inScope(x.a) || inScope(x.b)).length;
        const termOver = now > strtotime(current_term().ends_on + ' 23:59:59');

        const latest = studentsAll.slice().sort((a, b) => (b.enrolled_at || 0) - (a.enrolled_at || 0)).slice(0, 4);
        const atRisk = riskAll.slice(0, 3);

        const evaluations = facultyAll.filter(f => f.responses >= 60).sort((a, b) => cmp([b.rating, b.responses], [a.rating, a.responses])).slice(0, 4);
        const responses = sum(facultyAll.map(f => f.responses));
        const invited = sum(facultyScope.map(f => f.enrolled));
        const evalSummary = [
            [number_format(facultyAll.length), 'Faculty evaluated'],
            [responses ? number_format(sum(facultyAll.map(f => f.rating * f.responses)) / responses, 2) : '—', 'Average rating'],
            [invited ? pct(responses, invited) + '%' : '—', 'Response rate'],
        ];
        const seteAverage = responses ? sum(facultyAll.map(f => f.rating * f.responses)) / responses : null;
        stats.push(['SETE average score', seteAverage === null ? '—' : number_format(seteAverage, 2), responses ? number_format(responses) + ' responses · out of 5' : 'No evaluation responses', 'star', 'green', 'evaluations.html' + (collegeQs ? '?' + collegeQs : '')]);
        const seteCategories = EVAL_LABELS.map(label => [label, responses ? round(sum(facultyAll.map(f => f.categories[label] * f.responses)) / responses, 2) : 0]);

        // Proposed case-study warning thresholds, not adopted institutional policy.
        let warnings = [];
        if (classesAll.length && hasCurrentTerm && daysToDeadline < 0 && gradesPending > 0) {
            warnings.push(['Overdue grade submissions', gradesPending + ' class sections remain unsubmitted after the grade deadline. Contact the responsible faculty.']);
        }
        if (seteAverage !== null && seteAverage < 3.5 && responses >= 30) {
            warnings.push(['Review teaching support needs', 'The SETE average is below the proposed 3.5/5 review threshold with at least 30 responses. Check participation, course context and other evidence before acting.']);
        }
        if (is_historical_term()) warnings = [];

        const briefView = q('view') === 'brief';
        const hour = +date('G', now);
        const greeting = hour < 12 ? 'Good morning' : (hour < 18 ? 'Good afternoon' : 'Good evening');
        const collegeTotal = sum(breakdown.map(b => b[1]));
        const collegeMax = breakdown.length ? Math.max(...breakdown.map(b => b[1])) : 1;
        const gradeTotal = sum(gradeStatus.map(g => g[1]));
        const gradePct = Math.round(gradeStatus[0][1] / Math.max(1, gradeTotal) * 100);
        const trendVals = riskTrend.map(t => t[1]);
        const trendChange = (trendVals[0] || 0) > 0 ? round((1 - last(trendVals) / trendVals[0]) * 100, 1) : null;
        const pipelineMax = Math.max(1, pipeline[0][1]);
        const conversion = round(last(pipeline)[1] / pipelineMax * 100, 1);
        const tones = ['blue', 'purple', 'green'];

        let h = page_start('overview', 'Overview', user);
        h += '<div class="dashboard-view' + (briefView ? ' dashboard-view--brief' : '') + '">'
            + '<section class="page-head"><div><div class="eyebrow">' + e(date('l, F j, Y', now).toUpperCase()) + '</div>'
            + '<h1>' + (briefView ? 'Academic dashboard' : e(greeting) + ', ' + e(user.name) + '.') + '</h1>'
            + '<p class="muted">Academic monitoring for the authorized academic administrator.</p></div>'
            + '<form class="filters" method="get" action="index.html">' + (briefView ? '<input type="hidden" name="view" value="brief">' : '')
            + '<a class="btn btn--ghost btn--sm" href="' + e(url_with({ view: briefView ? null : 'brief' })) + '">' + (briefView ? 'Full overview' : 'Dashboard brief') + '</a>'
            + '<div class="select"><select name="college" aria-label="College" data-autosubmit><option value="">All colleges</option>'
            + college_names().map(c => '<option value="' + e(c) + '"' + (c === collegeFilter ? ' selected' : '') + '>' + e(c) + '</option>').join('')
            + '</select>' + icon('chev-d', 16) + '</div></form></section>';

        h += '<section class="grid grid-5" id="bat405Kpis" aria-label="Five institutional KPI cards">'
            + stats.map(s => stat_card(s[0], s[1], e(s[2]), s[3], s[4], s[5])).join('') + '</section>';
        for (const [label, text] of warnings) h += '<section class="notice" role="alert" data-dashboard-warning><span><strong>' + e(label) + '</strong> · ' + e(text) + '</span></section>';

        // Institutional KPIs
        h += '<section class="card panel"><div class="panel-head"><div><h2>Institutional KPIs</h2><p class="muted small">School-wide academic performance at a glance</p></div>'
            + '<span class="live"><span class="live-dot"></span>Updated today, ' + e(date('g:i A', now)) + '</span></div><div class="grid grid-3 kpis">'
            + '<a class="kpi kpi--link" href="enrollment.html' + (collegeQs ? '?' + e(collegeQs) : '') + '"><div class="kpi-head"><span class="kpi-title">' + e(breakdownLabel) + '</span>'
            + (enrollmentGrowth !== null ? '<span class="pill pill--green">' + icon('arrow-up', 12) + ' ' + e(String(enrollmentGrowth)) + '%</span>' : '') + '</div>'
            + '<div class="kpi-value">' + number_format(collegeTotal) + '</div><ul class="bars" data-chart="enrollment">'
            + breakdown.map(([name, n]) => '<li data-tip="' + e(name + '|' + number_format(n) + ' students · ' + pct(n, collegeTotal, 1) + '% of enrollment') + '">'
                + '<span class="bars-label">' + e(name) + '</span><span class="bars-track"><span class="bars-fill" style="--w: ' + round(n / collegeMax * 88, 1) + '%"></span></span>'
                + '<span class="bars-num">' + number_format(n) + '</span></li>').join('')
            + '</ul>' + (!breakdown.length ? '<p class="empty">No enrollment records available.</p>' : '') + '</a>'
            + '<a class="kpi kpi--link" href="' + e(scoped('grades.html')) + '"><div class="kpi-head"><span class="kpi-title">Grade submission rate</span>'
            + '<span class="pill pill--grey">' + (!hasCurrentTerm ? 'No active term' : (daysToDeadline >= 0 ? 'Due ' + e(date('M j', deadline)) : 'Past due')) + '</span></div>'
            + '<div class="kpi-value">' + (gradeTotal ? gradePct + '%' : '—') + '</div>'
            + '<div data-chart="grade-submission">' + (gradeTotal ? donut_chart(gradeStatus, gradePct + '%', 'submitted') : '<p class="empty">No class sections available to calculate a submission rate.</p>') + '</div>'
            + (gradesPending > 0 ? '<div class="kpi-note tone-orange-soft">' + icon('alert', 14) + ' ' + gradesPending + ' classes still need final grades</div>' : '') + '</a>'
            + '<a class="kpi kpi--link" data-chart="risk-trend" href="' + e(scoped('at-risk.html')) + '"><div class="kpi-head"><span class="kpi-title">At-risk student trend</span>'
            + '<span class="pill pill--grey">' + (trendChange === null ? '' : icon('arrow-dn', 12)) + ' ' + (trendChange === null ? 'No prior baseline' : e(String(trendChange)) + '%') + '</span></div>'
            + '<div class="kpi-value">' + (trendVals.length ? last(trendVals) : '—') + '</div>' + line_chart(riskTrend) + '</a>'
            + '</div></section>';

        h += '<div class="dashboard-extended">' + prediction_panel();

        // Incoming enrollment
        h += '<section class="card panel panel--flush"><div class="panel-head panel-pad"><div><div class="eyebrow eyebrow--blue">' + e(intake_label()) + '</div>'
            + '<h2>Incoming student enrollment</h2><p class="muted small">' + (collegeFilter ? e(collegeFilter) + ' admissions and enrollment progress' : 'School-wide admissions and enrollment progress across all colleges') + '</p></div>'
            + '<a href="' + e(scoped('incoming.html')) + '" class="link-strong">View admissions report ' + icon('chev-r', 16) + '</a></div><div class="metric-row">'
            + admissions.map(a => '<div class="metric"><div class="metric-label">' + e(a[0]) + '</div><div class="metric-value">' + e(a[1]) + '</div>'
                + '<div class="metric-note' + (a[3] ? ' up' : '') + '">' + (a[3] ? icon('arrow-up', 11) + ' ' : '') + e(a[2]) + '</div></div>').join('')
            + '</div><div class="split"><div class="split-col"><div class="sub-head"><div><h3>Enrollment pipeline</h3><p class="muted xsmall">Conversion from application to enrollment</p></div>'
            + '<span class="conv">' + (appTotal ? e(String(conversion)) + '% overall conversion' : 'Conversion unavailable: no applications') + '</span></div>'
            + (appTotal ? '<div class="funnel">' + pipeline.map(([label, value, tone]) => '<div class="funnel-step funnel-' + e(tone) + '" style="--w: ' + round(value / pipelineMax * 100, 1) + '%" data-tip="'
                + e(label + '|' + number_format(value) + ' applicants · ' + pct(value, pipelineMax, 1) + '% of applications') + '"><span>' + e(label) + '</span><strong>' + number_format(value) + '</strong></div>').join('') + '</div>'
                : '<p class="empty">No admissions data available.</p>')
            + (incomplete > 0 ? '<div class="notice">' + icon('alert', 16) + '<span><strong>' + number_format(incomplete) + ' applicants</strong> still have incomplete requirements</span>'
                + '<a href="' + e(scoped('incoming.html?status=Pending+documents')) + '" class="notice-link">Review</a></div>' : '')
            + '</div><div class="split-col"><div class="sub-head"><div><h3>Recent applicants</h3><p class="muted xsmall">Latest admissions activity · <span id="applicantCount">' + recentApplicants.length + '</span> shown</p></div></div>'
            + '<div class="chips" role="tablist" aria-label="Filter applicants">'
            + ['All', 'For review', 'Qualified', 'Pending documents', 'Enrolled'].map((chip, i) => '<button class="chip' + (i === 0 ? ' is-active' : '') + '" data-filter="' + e(chip) + '">' + e(chip) + '</button>').join('')
            + '</div><ul class="list" id="applicantList">'
            + recentApplicants.map(ap => '<li class="list-row" data-status="' + e(ap.status) + '" data-href="incoming.html?' + e(qs({ q: ap.id })) + '">'
                + avatar(ap.name, status_tone(ap.status), 'avatar--lg') + '<div class="list-main"><strong>' + e(ap.name) + '</strong><span>' + e(ap.id) + ' · ' + e(ap.program_name) + '</span></div>'
                + tag(ap.status) + '<span class="list-time">' + e(rel_time(ap.updated)) + '</span>' + icon('chev-r', 16, 'chev') + '</li>').join('')
            + '<li class="list-empty"' + (recentApplicants.length ? ' hidden' : '') + '>No recent applicants with this status. <a class="link" href="incoming.html">See all applicants</a></li></ul></div></div></section>';

        // Schedule + grade submission
        h += '<section class="grid grid-2-1"><div id="schedule" class="card panel"><div class="panel-head"><div><h2>Campus class schedule</h2>'
            + '<p class="muted small">' + e(date('l', now)) + ' timetable · ' + e(current_term().name) + (termOver ? ' (classes ended)' : '') + '</p></div>'
            + '<a href="' + e(scoped('schedule.html?day=' + todayKey)) + '" class="link-strong">View full schedule ' + icon('chev-r', 16) + '</a></div><ul class="sched">'
            + schedule.map((cls, i) => {
                const tone = tones[i % 3];
                return '<li class="sched-row" data-href="schedule.html?' + e(qs({ day: todayKey, q: cls.code })) + '">'
                    + '<div class="sched-time"><strong>' + e(clock(cls.start)) + '</strong><span>' + e(clock(cls.end, true)) + '</span></div>'
                    + '<span class="sched-bar tone-bar-' + tone + '"></span><div class="sched-main"><div class="sched-title"><span class="code code--' + tone + '">' + e(cls.code) + '</span><strong>' + e(cls.title) + '</strong></div>'
                    + '<div class="sched-meta"><span>' + icon('users', 14) + ' ' + e(cls.section) + ' · ' + e(cls.faculty) + '</span>'
                    + '<span>' + icon(cls.mode === 'Online' ? 'video' : 'pin', 14) + ' ' + e(cls.room) + '</span>' + tag(cls.mode, null, 'tag--sm') + '</div></div>'
                    + icon('chev-r', 16, 'chev') + '</li>';
            }).join('')
            + (!schedule.length ? '<li class="list-empty">No classes scheduled today.</li>' : '')
            + '</ul><div class="panel-foot"><span>' + icon('building', 16) + ' Campus utilization <strong>' + totalRooms + ' rooms · ' + number_format(classesAll.length) + ' class sections</strong></span>'
            + '<a class="' + (conflictCount ? 'warn' : 'ok') + '" href="schedule.html#conflicts">' + icon('alert', 14) + ' ' + conflictCount + ' room conflict' + (conflictCount === 1 ? '' : 's') + ' flagged</a></div></div>'
            + '<div id="grades" class="card panel"><div class="panel-head"><div><h2>' + (collegeFilter ? e(collegeFilter) + ' grade submission' : 'School-wide grade submission') + '</h2>'
            + '<p class="muted small">' + (collegeFilter ? 'Encoding progress by year level' : 'College-level encoding progress') + '</p></div>'
            + '<a class="icon-btn" href="' + e(scoped('grades.html')) + '" aria-label="Open grade submissions">' + icon('more', 18) + '</a></div>'
            + (!gradeByCollege.length ? '<p class="empty">No class sections available.</p>' : '') + '<ul class="progress-list">'
            + gradeByCollege.map(g => {
                const done = g.pending === 0;
                return '<li><a href="' + e(g.link) + '"><div class="progress-head"><span><strong>' + e(g.college) + '</strong> <span class="muted xsmall">' + g.classes + ' classes</span></span>'
                    + '<span class="tag tag--sm ' + (done ? 'tag--green' : 'tag--orange') + '">' + (done ? icon('check', 12) + ' Submitted' : 'In progress') + '</span></div>'
                    + '<div class="progress" data-tip="' + e(g.college + '|' + g.pct + '% of grades encoded|' + (g.classes - g.pending) + ' of ' + g.classes + ' classes submitted') + '"><span style="--w: ' + g.pct + '%"></span></div>'
                    + '<div class="progress-foot"><span>' + g.pct + '% encoded</span><span>' + (done ? 'All submitted' : g.pending + ' pending') + '</span></div></a></li>';
            }).join('')
            + '</ul><a href="' + e(scoped('grades.html?status=pending')) + '" class="btn btn--primary btn--block">Review all pending submissions ' + icon('chev-r', 16) + '</a></div></section>';

        // Student enrollment + risk
        h += '<section class="grid grid-2"><div class="card panel panel--list"><div class="panel-head panel-pad"><div><h2>Student enrollment</h2><p class="muted small">Latest enrollees · ' + number_format(collegeTotal) + ' students</p></div>'
            + '<a href="' + e(scoped('enrollment.html')) + '" class="link-strong">View all students ' + icon('chev-r', 16) + '</a></div><ul class="list list--padded">'
            + latest.map(st => '<li class="list-row" data-href="enrollment.html?' + e(qs({ q: st.id })) + '">' + avatar(st.name, null, 'avatar--lg')
                + '<div class="list-main"><strong>' + e(st.name) + '</strong><span>' + e(st.id) + '</span></div>'
                + '<div class="list-right"><strong>' + e(st.section) + ' · ' + e(st.college) + '</strong><span>Enrolled ' + e(rel_time(st.enrolled_at)) + '</span></div>' + icon('chev-r', 16, 'chev') + '</li>').join('')
            + (!latest.length ? '<li class="list-empty">No student records loaded.</li>' : '') + '</ul></div>'
            + '<div class="card panel panel--list"><div class="panel-head panel-pad"><div><h2>School-wide risk monitoring</h2><p class="muted small">Students across all colleges who may need support</p></div>'
            + tag(hasRiskEvidence ? riskAll.length + ' observed cases' : 'Assessment unavailable', hasRiskEvidence ? 'red' : 'slate') + '</div><ul class="list list--padded">'
            + atRisk.map(r => '<li class="list-row" data-href="at-risk.html?' + e(qs({ q: r.id })) + '">' + avatar(r.name, 'slate', 'avatar--lg')
                + '<div class="list-main"><strong>' + e(r.name) + '</strong><span>' + e(r.college) + ' · ' + e(r.section) + '</span></div>'
                + '<div class="list-right"><strong class="text-' + e(status_tone(r.type)) + '">' + e(r.type) + '</strong><span>' + e(r.detail) + '</span></div>' + icon('chev-r', 16, 'chev') + '</li>').join('')
            + (!atRisk.length ? '<li class="list-empty">' + (hasRiskEvidence ? 'No flags in the available evidence.' : 'No grade or backlog evidence available.') + '</li>' : '')
            + '</ul><div class="panel-pad panel-pad--bottom"><a href="' + e(scoped('at-risk.html')) + '" class="btn btn--ghost btn--block">'
            + (hasRiskEvidence ? 'Review ' + riskAll.length + ' observed at-risk students' : 'Open academic risk monitoring') + '</a></div></div></section>';

        // Faculty evaluation
        h += '<section class="eval"><div class="eval-left"><div class="eyebrow eyebrow--light">Faculty evaluation</div><h2>University-wide evaluation results</h2>'
            + '<p>Review anonymous, aggregate student feedback for every faculty member across all colleges. Individual student responses remain private.</p>'
            + '<a href="' + e(scoped('evaluations.html')) + '" class="btn btn--white">View complete report ' + icon('chev-r', 16) + '</a><div class="eval-stats">'
            + evalSummary.map(([v, l]) => '<div><strong>' + e(v) + '</strong><span>' + e(l) + '</span></div>').join('')
            + '</div></div><div class="eval-right"><div class="eval-cols"><span>Faculty member</span><span>Rating</span></div><ul>'
            + evaluations.map(ev => '<li class="eval-row" data-href="evaluations.html?' + e(qs({ q: ev.name.replace(/^(Dr|Prof|Engr)\.\s+/, '') })) + '">' + avatar(ev.name, 'glass', 'avatar--lg')
                + '<div class="list-main"><strong>' + e(ev.name) + '</strong><span>' + e(ev.college) + ' · ' + ev.responses + ' responses</span></div>'
                + '<div class="eval-rating"><strong>' + number_format(ev.rating, 1) + '</strong>' + stars(ev.rating) + '</div>' + icon('chev-r', 16, 'chev') + '</li>').join('')
            + (!evaluations.length ? '<li class="list-empty">' + (responses ? 'No faculty meet the 60-response display threshold.' : 'No faculty evaluation responses recorded.') + '</li>' : '')
            + '</ul></div></section></div>';

        h += '<section class="card panel" aria-label="SETE dimension visualization"><div class="panel-head"><div><h2>SETE evaluation dimensions</h2>'
            + '<p class="muted small">Response-weighted average scores out of 5 · ' + e(current_term().name) + '</p></div><a class="link-strong" href="' + e(scoped('evaluations.html')) + '">View SETE summaries</a></div>'
            + '<div data-chart="sete-dimensions">' + (responses ? bar_list(seteCategories, 5, 'rating', 'orange block') : '<p class="empty">No evaluation responses available. Teaching effectiveness cannot be assessed yet.</p>') + '</div>'
            + '<p class="muted small">Consider participation, sample size, course context and other evidence. SETE alone must not determine personnel decisions. External predictions do not affect these scores.</p></section>';
        if (briefView) {
            h += '<p class="muted small">BAT 405 · Institutional records only. External demonstration predictions: <a href="predictions.html">Outcome prediction</a>. Proposed review thresholds: any overdue grade submission, urgent support case, or SETE below 3.5/5 with at least 30 responses. Check participation and context.</p>';
        }
        return h + '</div>' + page_end();
    }

    /* ================================================================== */
    /* Incoming enrollment                                                 */
    /* ================================================================== */

    function incoming() {
        const user = P.require_login();
        P.require_permission('academic.view', user);
        const all = P.applicants();
        const college = valid_college(q('college'));
        const status = q('status'), type = q('type'), search = q('q');

        const scope = filter_rows(all, { college });
        let rows = filter_rows(scope, { type });
        rows = search_rows(rows, search, ['name', 'id', 'program', 'program_name']);
        const statusCounts = count_values(rows.map(r => r.status));
        rows = filter_rows(rows, { status }).slice().sort((a, b) => b.submitted - a.submitted);

        maybe_export_csv(rows, [
            ['Applicant ID', 'id'], ['Name', 'name'], ['Program', 'program_name'], ['College', 'college'], ['Type', 'type'],
            ['SHS strand', r => r.strand || ''], ['SHS average / GWA', r => (r.prior === null ? '' : r.prior)], ['Exam score', r => (r.score === null ? '' : r.score)],
            ['Status', 'status'], ['Missing requirements', r => r.missing.join('; ')], ['Submitted', r => date('Y-m-d H:i', r.submitted)],
        ], 'applicants');
        const page = paginate(rows, 15);

        const counts = count_values(scope.map(a => a.status));
        const total = scope.length;
        const forReview = counts['For review'] || 0;
        const enrolled = counts.Enrolled || 0;
        const qualified = enrolled + (counts.Qualified || 0) + (counts['Pending documents'] || 0);
        const reviewed = total - forReview;
        const incomplete = scope.filter(a => a.status === 'Pending documents');

        const funnel = [
            ['Applications', total, 'p1', null, url_with({ status: '' }) + '#list'],
            ['Examined', reviewed, 'p2', pct(reviewed, total, 1) + '% of applications', null],
            ['Qualified', qualified, 'p3', pct(qualified, reviewed, 1) + '% passed the exam', url_with({ status: 'Qualified' }) + '#list'],
            ['Enrolled', enrolled, 'p4', pct(enrolled, qualified, 1) + '% of qualified', url_with({ status: 'Enrolled' }) + '#list'],
        ];

        const groupKey = college ? 'program_name' : 'college';
        const groups = new Map();
        for (const a of scope) {
            const g = groups.get(a[groupKey]) || { total: 0, qualified: 0, enrolled: 0, review: 0 };
            g.total++;
            if (['Qualified', 'Pending documents', 'Enrolled'].includes(a.status)) g.qualified++;
            if (a.status === 'Enrolled') g.enrolled++;
            if (a.status === 'For review') g.review++;
            groups.set(a[groupKey], g);
        }
        const groupList = Array.from(groups).sort((a, b) => b[1].total - a[1].total);

        const missingMap = new Map(REQUIREMENTS.map(r => [r, 0]));
        for (const a of incomplete) for (const m of a.missing) missingMap.set(m, (missingMap.get(m) || 0) + 1);
        const missingCounts = bySizeDesc(Array.from(missingMap));

        const typeCounts = count_values(scope.map(a => a.type));
        const scores = scope.map(a => a.score).filter(Boolean);
        const avgScore = scores.length ? round(sum(scores) / scores.length, 1) : 0;
        const prevIntake = +meta('prev_intake_applications', '0');

        const statusOptions = [['', 'All'], ['For review', 'For review'], ['Qualified', 'Qualified'], ['Pending documents', 'Pending documents'], ['Enrolled', 'Enrolled'], ['Not qualified', 'Not qualified']];
        const chipCounts = Object.assign({ '': sum(Object.values(statusCounts)) }, statusCounts);

        let h = page_start('incoming', 'Incoming enrollment', user);
        h += page_head('Incoming enrollment', e(intake_label()) + ' admissions' + (college ? ' · ' + e(college) : ' across all colleges'),
            '<form method="get" class="filters">' + select_filter('college', college_names(), college, 'All colleges') + '</form>' + export_button()
            + modal_button('applicantModal', icon('plus', 16) + ' Add applicant'));

        h += '<section class="grid grid-4">'
            + stat_card('Total applications', number_format(total), college ? pct(total, all.length) + '% of all applications' : icon('arrow-up', 11) + ' ' + pct(total - prevIntake, +meta('prev_intake_applications', '1'), 1) + '% from last intake', 'file', 'blue')
            + stat_card('For review', number_format(forReview), pct(forReview, total) + '% of applications', 'clock', 'purple', url_with({ status: 'For review' }))
            + stat_card('Qualified', number_format(qualified), pct(qualified, reviewed) + '% of examinees passed', 'check-sq', 'orange', url_with({ status: 'Qualified' }))
            + stat_card('Fully enrolled', number_format(enrolled), pct(enrolled, qualified) + '% of qualified students', 'graduation', 'green', url_with({ status: 'Enrolled' }))
            + '</section>';

        h += '<section class="grid grid-2-1"><div class="card panel"><div class="panel-head"><div><h2>Applications by ' + (college ? 'program' : 'college') + '</h2><p class="muted small">Where applicants are in the process</p></div></div>'
            + '<div class="table-wrap"><table class="data"><thead><tr><th>' + (college ? 'Program' : 'College') + '</th><th class="num">Applied</th><th class="num">For review</th><th class="num">Qualified</th><th class="num">Enrolled</th><th>Conversion</th></tr></thead><tbody>'
            + groupList.map(([name, g]) => {
                const conv = g.total ? g.enrolled / g.total * 100 : 0;
                return '<tr><td><strong>' + e(name) + '</strong></td><td class="num">' + number_format(g.total) + '</td><td class="num">' + number_format(g.review) + '</td>'
                    + '<td class="num">' + number_format(g.qualified) + '</td><td class="num">' + number_format(g.enrolled) + '</td>'
                    + '<td class="nowrap"><div class="mini-bar mini-bar--sm"><span style="--w: ' + round(Math.min(100, conv * 2), 1) + '%"></span></div><span class="mini-bar-num">' + number_format(conv, 1) + '%</span></td></tr>';
            }).join('')
            + '</tbody></table></div></div>'
            + '<div class="card panel"><div class="panel-head"><div><h2>Enrollment pipeline</h2><p class="muted small">' + pct(enrolled, total, 1) + '% overall conversion</p></div></div><div class="funnel">'
            + funnel.map(([label, value, tone, note, href]) => '<div class="funnel-step funnel-' + tone + (href ? ' is-link' : '') + '" style="--w: ' + (total ? round(value / total * 100, 1) : 0) + '%" data-tip="'
                + e(label + '|' + number_format(value) + ' applicants · ' + pct(value, total, 1) + '% of all applications' + (note ? '|' + note : '') + (href ? '|Click to list them' : '')) + '"'
                + (href ? ' data-href="' + e(href) + '" tabindex="0" role="link"' : '') + '><span>' + e(label) + '</span><strong>' + number_format(value) + '</strong></div>').join('')
            + '</div><ul class="kv">'
            + funnel.slice(1).map(([label, , , note]) => '<li><span>' + e(label) + '</span><strong>' + e(note) + '</strong></li>').join('')
            + '<li><span>Average entrance exam score</span><strong>' + e(String(avgScore)) + '</strong></li>'
            + '<li><span>Freshmen · Transferees</span><strong>' + number_format(typeCounts.Freshman || 0) + ' · ' + number_format(typeCounts.Transferee || 0) + '</strong></li></ul></div></section>';

        h += '<section class="card panel"><div class="panel-head"><div><h2>Missing requirements</h2><p class="muted small">' + number_format(incomplete.length) + ' applicants still have incomplete requirements. Each bar counts applicants missing that document.</p></div>'
            + '<a class="link-strong" href="' + e(url_with({ status: 'Pending documents' })) + '">Show these applicants ' + icon('chev-r', 16) + '</a></div>'
            + bar_list(missingCounts, null, 'number', 'orange') + '</section>';

        h += '<section class="card panel panel--flush" id="list"><div class="table-toolbar"><div><h2>Applicants</h2><p class="muted small">Newest applications first</p></div>'
            + '<form method="get" class="toolbar-form" action="incoming.html#list"><input type="hidden" name="college" value="' + e(college) + '"><input type="hidden" name="status" value="' + e(status) + '">'
            + '<label class="search-field">' + icon('search', 16) + '<input type="search" name="q" value="' + e(search) + '" placeholder="Name, applicant ID or program"></label>'
            + select_filter('type', ['Freshman', 'Transferee'], type, 'All types') + '</form></div>'
            + '<div class="toolbar-chips">' + chip_links('status', statusOptions, status, chipCounts) + '</div>'
            + '<div class="table-wrap"><table class="data data--rows"><thead><tr><th>Applicant</th><th>Program</th><th>Type</th><th class="num">Exam</th><th>Requirements</th><th>Status</th><th>Applied</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody>';
        for (const a of page.items) {
            const have = REQUIREMENTS.length - a.missing.length;
            let dots = '';
            for (let i = 0; i < REQUIREMENTS.length; i++) dots += '<span class="' + (i < have ? 'on' : '') + '"></span>';
            h += '<tr><td><div class="cell-person">' + avatar(a.name, status_tone(a.status)) + '<div><strong>' + e(a.name) + '</strong><span>' + e(a.id) + '</span></div></div></td>'
                + '<td class="col-wide"><div class="cell-stack"><strong>' + e(a.program_name) + '</strong><span>' + e(a.college) + '</span></div></td>'
                + '<td><div class="cell-stack"><strong>' + e(a.type) + '</strong><span class="nowrap">' + (a.strand ? e(a.strand) + ' · ' + number_format(a.prior, 1) : 'GWA ' + number_format(a.prior || 0, 2)) + '</span></div></td>'
                + '<td class="num">' + (a.score === null ? '<span class="muted">—</span>' : a.score) + '</td>'
                + '<td><div class="req" title="' + e(a.missing.length ? 'Missing: ' + a.missing.join(', ') : 'All requirements submitted') + '">' + dots + '<em>' + have + '/' + REQUIREMENTS.length + '</em></div></td>'
                + '<td>' + tag(a.status) + '</td><td class="nowrap muted">' + e(rel_time(a.submitted)) + '</td>'
                + '<td>' + row_actions('applicantModal', {
                    id: a.aid, first_name: a.first, middle_name: a.middle, last_name: a.last, sex: a.sex, email: a.email,
                    program: a.program, type: a.type, shs_strand: a.strand || '', prior_gwa: a.prior === null ? '' : a.prior, exam_score: a.score === null ? '' : a.score,
                    status: a.status, req: REQUIREMENTS.map((r, i) => (a.missing.includes(r) ? null : i)).filter(i => i !== null),
                }, 'Edit applicant ' + a.id, 'applicant_delete', a.aid, 'Delete applicant ' + a.name + ' (' + a.id + ')? This can’t be undone.') + '</td></tr>';
        }
        if (!page.items.length) h += '<tr><td colspan="8" class="empty">No applicants match these filters.</td></tr>';
        h += '</tbody></table></div>' + pagination(page, 'applicants') + '</section>' + P.applicant_modal();
        return h + page_end();
    }

    /* ================================================================== */
    /* Enrollment                                                          */
    /* ================================================================== */

    function enrollment() {
        const user = P.require_login();
        P.require_permission('academic.view', user);
        const college = valid_college(q('college'));
        const year = q('year'), status = q('status'), program = q('program'), search = q('q');
        const riskIds = P.at_risk_ids();

        const scope = filter_rows(P.students(), { college });
        let rows = filter_rows(scope, { year, program });
        rows = search_rows(rows, search, ['name', 'id', 'program', 'program_name', 'section']);
        const statusCounts = count_values(rows.map(r => r.status));
        rows = filter_rows(rows, { status });

        maybe_export_csv(rows, [
            ['Student no.', 'id'], ['Name', 'name'], ['Program', 'program_name'], ['Section', 'section'], ['College', 'college'],
            ['Year level', r => year_label(r.year)], ['Status', 'status'], ['Units', 'units'], ['At risk', r => (riskIds.has(r.id) ? 'Yes' : '')],
        ], 'students');
        const page = paginate(rows, 20);

        const total = scope.length;
        const counts = count_values(scope.map(s => s.status));
        const activeUnits = scope.filter(s => s.units > 0).map(s => s.units);
        const avgUnits = activeUnits.length ? sum(activeUnits) / activeUnits.length : 0;

        const matrix = new Map();
        for (const s of scope) {
            const key = college ? s.program_name : s.college;
            if (!matrix.has(key)) matrix.set(key, { 1: 0, 2: 0, 3: 0, 4: 0 });
            matrix.get(key)[s.year] = (matrix.get(key)[s.year] || 0) + 1;
        }
        const rowsum = m => m[1] + m[2] + m[3] + m[4];
        const matrixList = Array.from(matrix).sort((a, b) => rowsum(b[1]) - rowsum(a[1]));
        const yearTotals = { 1: 0, 2: 0, 3: 0, 4: 0 };
        for (const [, m] of matrixList) for (const y of [1, 2, 3, 4]) yearTotals[y] += m[y];

        const programCounts = bySizeDesc(countBy(scope, 'program'));
        const programOptions = [];
        for (const [name, c] of colleges()) if (!college || name === college) programOptions.push(...c.programs);

        const prevTerm = +meta('prev_term_enrollment', '0');
        const statusOptions = [['', 'All'], ['Regular', 'Regular'], ['Irregular', 'Irregular'], ['On leave', 'On leave']];
        const chipCounts = Object.assign({ '': sum(Object.values(statusCounts)) }, statusCounts);

        let h = page_start('enrollment', 'Enrollment', user);
        h += page_head('Enrollment', e(current_term().name) + ' · ' + number_format(total) + ' enrolled students' + (college ? ' in ' + e(college) : ' across ' + colleges().size + ' colleges'),
            '<form method="get" class="filters">' + select_filter('college', college_names(), college, 'All colleges') + '</form>' + export_button()
            + modal_button('studentModal', icon('plus', 16) + ' Add student'));

        h += '<section class="grid grid-4">'
            + stat_card('Enrolled students', number_format(total), college ? pct(total, P.students().length) + '% of all students' : (prevTerm > 0 ? icon('arrow-up', 11) + ' ' + pct(total - prevTerm, prevTerm, 1) + '% from last term' : 'No prior enrollment baseline'), 'users', 'blue')
            + stat_card('Regular', number_format(counts.Regular || 0), pct(counts.Regular || 0, total) + '% full curriculum load', 'check-sq', 'green', url_with({ status: 'Regular' }) + '#list')
            + stat_card('Irregular', number_format(counts.Irregular || 0), pct(counts.Irregular || 0, total) + '% reduced or mixed load', 'clock', 'orange', url_with({ status: 'Irregular' }) + '#list')
            + stat_card('On leave', number_format(counts['On leave'] || 0), activeUnits.length ? 'Average load ' + number_format(avgUnits, 1) + ' units' : 'No enrolled load records', 'user-x', 'purple', url_with({ status: 'On leave' }) + '#list')
            + '</section>';

        h += '<section class="grid grid-2-1"><div class="card panel"><div class="panel-head"><div><h2>Students by ' + (college ? 'program' : 'college') + ' and year level</h2><p class="muted small">Click a number to see those students</p></div></div>'
            + '<div class="table-wrap"><table class="data"><thead><tr><th>' + (college ? 'Program' : 'College') + '</th><th class="num">1st</th><th class="num">2nd</th><th class="num">3rd</th><th class="num">4th</th><th class="num">Total</th><th>Share</th></tr></thead><tbody>'
            + matrixList.map(([name, m]) => {
                const s = rowsum(m);
                const found = programOptions.find(p => p[1] === name);
                const base = college ? { program: found ? found[0] : '' } : { college: name };
                return '<tr><td><strong>' + e(name) + '</strong></td>'
                    + [1, 2, 3, 4].map(y => '<td class="num"><a class="cell-link" href="' + e(url_with(Object.assign({}, base, { year: y })) + '#list') + '">' + number_format(m[y]) + '</a></td>').join('')
                    + '<td class="num"><a class="cell-link" href="' + e(url_with(base) + '#list') + '"><strong>' + number_format(s) + '</strong></a></td>'
                    + '<td class="nowrap"><div class="mini-bar"><span style="--w: ' + (total ? round(s / total * 100 * 2.5, 1) : 0) + '%"></span></div><span class="mini-bar-num">' + pct(s, total, 1) + '%</span></td></tr>';
            }).join('')
            + '</tbody><tfoot><tr><td>Total</td>' + [1, 2, 3, 4].map(y => '<td class="num">' + number_format(yearTotals[y]) + '</td>').join('') + '<td class="num">' + number_format(total) + '</td><td></td></tr></tfoot></table></div></div>'
            + '<div class="stack"><div class="card panel"><div class="panel-head"><div><h2>Year level</h2><p class="muted small">Share of enrolled students</p></div></div>'
            + bar_list([1, 2, 3, 4].map(y => [year_label(y), yearTotals[y]]), null, 'number', 'purple', Object.fromEntries([1, 2, 3, 4].map(y => [year_label(y), url_with({ year: y }) + '#list'])))
            + '</div><div class="card panel"><div class="panel-head"><div><h2>Largest programs</h2><p class="muted small">Top ' + Math.min(6, programCounts.length) + ' by enrollment</p></div></div>'
            + bar_list(programCounts.slice(0, 6), null, 'number', '', Object.fromEntries(programCounts.map(([code]) => [code, url_with({ program: code }) + '#list'])))
            + '</div></div></section>';

        h += '<section class="card panel panel--flush" id="list"><div class="table-toolbar"><div><h2>Student directory</h2><p class="muted small">Sorted by name</p></div>'
            + '<form method="get" class="toolbar-form" action="enrollment.html#list"><input type="hidden" name="college" value="' + e(college) + '"><input type="hidden" name="status" value="' + e(status) + '">'
            + '<label class="search-field">' + icon('search', 16) + '<input type="search" name="q" value="' + e(search) + '" placeholder="Name, student no. or section"></label>'
            + select_filter('program', programOptions, program, 'All programs') + select_filter('year', P.YEAR_OPTIONS, year, 'All years') + '</form></div>'
            + '<div class="toolbar-chips">' + chip_links('status', statusOptions, status, chipCounts) + '</div>'
            + '<div class="table-wrap"><table class="data data--rows"><thead><tr><th>Student</th><th>Program</th><th>Section</th><th>Year</th><th class="num">Units</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody>';
        for (const s of page.items) {
            h += '<tr><td><div class="cell-person">' + avatar(s.name) + '<div><strong>' + e(s.name) + '</strong><span>' + e(s.id) + '</span></div></div></td>'
                + '<td><div class="cell-stack"><strong>' + e(s.program_name) + '</strong><span>' + e(s.college) + '</span></div></td>'
                + '<td>' + e(s.section) + '</td><td class="nowrap">' + e(year_label(s.year)) + '</td>'
                + '<td class="num">' + (s.units || '<span class="muted">—</span>') + '</td>'
                + '<td class="nowrap">' + tag(s.status) + (riskIds.has(s.id) ? '<a href="at-risk.html?' + e(qs({ q: s.id })) + '" class="tag tag--red">At risk</a>' : '') + '</td>'
                + '<td>' + row_actions('studentModal', {
                    id: s.sid, first_name: s.first, middle_name: s.middle, last_name: s.last, sex: s.sex, birthdate: s.birthdate,
                    email: s.email, program: s.program, year: s.year, block: s.block, status: s.status, back_subjects: s.back,
                }, 'Edit student ' + s.id, 'student_delete', s.sid, 'Delete student ' + s.name + ' (' + s.id + ')? Their class enrollments and grades are deleted too.') + '</td></tr>';
        }
        if (!page.items.length) h += '<tr><td colspan="7" class="empty">No students match these filters.</td></tr>';
        h += '</tbody></table></div>' + pagination(page, 'students') + '</section>' + P.student_modal();
        return h + page_end();
    }

    /* ================================================================== */
    /* Campus schedule                                                     */
    /* ================================================================== */

    function schedule() {
        const user = P.require_login();
        P.require_permission('academic.view', user);
        const days = new Map(WEEK_DAYS);
        const now = app_now();
        const todayKey = date('D', now) === 'Sun' ? 'Mon' : date('D', now);
        const inSession = now <= strtotime(current_term().ends_on + ' 23:59:59');
        const day = days.has(q('day')) ? q('day') : todayKey;
        const college = valid_college(q('college'));
        const mode = q('mode'), search = q('q');

        const inCollege = c => college === '' || c.college === college;
        const allClasses = P.classes().filter(inCollege);
        const daySessions = P.sessions_on(day).filter(inCollege);
        let rows = search_rows(daySessions, search, ['code', 'title', 'section', 'faculty', 'room']);
        rows = filter_rows(rows, { mode });

        maybe_export_csv(rows, [
            ['Day', () => days.get(day)], ['Start', r => clock(r.start, true)], ['End', r => clock(r.end, true)],
            ['Code', 'code'], ['Title', 'title'], ['Section', 'section'], ['Faculty', 'faculty'], ['College', 'college'],
            ['Room', 'room'], ['Mode', 'mode'], ['Students', 'students'],
        ], 'schedule-' + day.toLowerCase());
        const page = paginate(rows, 40);

        const slots = new Map();
        for (const s of page.items) { if (!slots.has(s.start)) slots.set(s.start, []); slots.get(s.start).push(s); }
        const slotList = Array.from(slots).sort((a, b) => a[0] - b[0]);

        const scopeColleges = college ? [[college, colleges().get(college)]] : Array.from(colleges());
        const totalRooms = sum(scopeColleges.map(([, c]) => c.rooms.length));
        const roomsToday = new Set(daySessions.filter(s => s.mode === 'Onsite').map(s => s.room));
        const onlineToday = daySessions.filter(s => s.mode === 'Online').length;
        const conflicts = P.room_conflicts().filter(x => inCollege(x.a) || inCollege(x.b));
        const offCampus = allClasses.filter(c => c.mode === 'Off-campus').length;

        const utilization = scopeColleges.map(([name, c]) => {
            const used = c.rooms.filter(r => roomsToday.has(r)).length;
            const share = c.rooms.length ? used / c.rooms.length * 100 : 0;
            return [name, [share, used + ' of ' + c.rooms.length + ' rooms · ' + Math.round(share) + '%']];
        });
        const dayCounts = {};
        const weekly = WEEK_DAYS.map(([k, label]) => { dayCounts[k] = P.sessions_on(k).filter(inCollege).length; return [label, dayCounts[k]]; });

        let h = page_start('schedule', 'Campus schedule', user);
        h += page_head('Campus schedule',
            'Weekly timetable · ' + e(current_term().name) + ' · ' + number_format(allClasses.length - offCampus) + ' class sections meet each week' + (college ? ' in ' + e(college) : '') + (inSession ? '' : ' · Classes ended ' + e(date('M j', strtotime(current_term().ends_on)))),
            '<form method="get" class="filters"><input type="hidden" name="day" value="' + e(day) + '">' + select_filter('college', college_names(), college, 'All colleges') + '</form>' + export_button()
            + modal_button('classModal', icon('plus', 16) + ' Add class', 'btn btn--primary btn--sm', { meetings: [{ day, start: '08:00', end: '09:00', room: '' }] }));

        h += '<nav class="day-tabs" aria-label="Day of week">'
            + WEEK_DAYS.map(([k]) => '<a href="' + e(url_with({ day: k })) + '" class="day-tab' + (k === day ? ' is-active' : '') + '"' + (k === day ? ' aria-current="true"' : '') + '>'
                + '<span>' + e(k) + (k === todayKey && inSession ? ' <em>Today</em>' : '') + '</span><strong>' + dayCounts[k] + '</strong><small>sessions</small></a>').join('')
            + '</nav>';

        h += '<section class="grid grid-4">'
            + stat_card('Sessions', number_format(daySessions.length), 'Scheduled on ' + e(days.get(day)), 'calendar', 'blue')
            + stat_card('Rooms in use', roomsToday.size + ' / ' + totalRooms, pct(roomsToday.size, totalRooms) + '% of campus rooms', 'building', 'purple')
            + stat_card('Online sessions', number_format(onlineToday), 'Google Meet and MS Teams', 'video', 'green', url_with({ mode: 'Online' }) + '#list')
            + stat_card('Room conflicts', String(conflicts.length), conflicts.length ? 'Double-booked rooms this week' : 'No double bookings', 'alert', conflicts.length ? 'red' : 'green', '#conflicts')
            + '</section>';

        if (conflicts.length) {
            h += '<section class="card panel panel--warn" id="conflicts"><div class="panel-head"><div><h2>' + conflicts.length + ' room conflict' + (conflicts.length === 1 ? '' : 's') + ' flagged</h2>'
                + '<p class="muted small">Two classes are booked in the same room at the same time. Move one of them to a free room.</p></div></div><ul class="conflicts">'
                + conflicts.map(x => '<li><div class="conflict-where"><strong>' + icon('pin', 14) + ' ' + e(x.room) + '</strong><span>' + e(x.days.join(', ')) + ' · ' + e(clock(x.start, true)) + '–' + e(clock(x.end, true)) + '</span></div>'
                    + [x.a, x.b].map(c => '<div class="conflict-class"><span class="code code--red">' + e(c.code) + '</span><div><strong>' + e(c.title) + '</strong><span>' + e(c.section) + ' · ' + e(c.faculty) + '</span></div>'
                        + modal_button('classModal', icon('edit', 14) + ' Move', 'btn btn--ghost btn--xs', P.class_fill(c), 'Edit class ' + c.key) + '</div>').join('') + '</li>').join('')
                + '</ul></section>';
        }

        h += '<section class="grid grid-2-1"><div class="card panel panel--flush" id="list"><div class="table-toolbar"><div><h2>' + e(days.get(day)) + ' timetable</h2>'
            + '<p class="muted small">' + number_format(rows.length) + ' of ' + number_format(daySessions.length) + ' sessions match</p></div>'
            + '<form method="get" class="toolbar-form" action="schedule.html#list"><input type="hidden" name="day" value="' + e(day) + '"><input type="hidden" name="college" value="' + e(college) + '">'
            + '<label class="search-field">' + icon('search', 16) + '<input type="search" name="q" value="' + e(search) + '" placeholder="Class, section, faculty or room"></label>'
            + select_filter('mode', ['Onsite', 'Online'], mode, 'All modes') + '</form></div>';
        for (const [start, list] of slotList) {
            h += '<div class="slot-head"><strong>' + e(clock(start, true)) + '</strong><span>' + list.length + ' class' + (list.length === 1 ? '' : 'es') + '</span></div><ul class="sched sched--dense">';
            for (const cls of list) {
                const tone = status_tone(cls.mode) === 'green' ? 'green' : 'blue';
                h += '<li class="sched-row"><span class="sched-bar tone-bar-' + tone + '"></span><div class="sched-main">'
                    + '<div class="sched-title"><span class="code code--' + tone + '">' + e(cls.code) + '</span><strong>' + e(cls.title) + '</strong></div>'
                    + '<div class="sched-meta"><span>' + icon('users', 14) + ' ' + e(cls.section) + ' · ' + e(cls.faculty) + '</span>'
                    + '<span>' + icon(cls.mode === 'Online' ? 'video' : 'pin', 14) + ' ' + e(cls.room) + '</span><span>' + icon('building', 14) + ' ' + e(cls.college) + '</span></div></div>'
                    + '<div class="sched-side">' + tag(cls.mode, null, 'tag--sm') + '<span class="muted xsmall">' + e(clock(cls.start)) + '–' + e(clock(cls.end, true)) + ' · ' + cls.students + ' students</span></div>'
                    + '<div class="row-actions"><a class="icon-btn" href="class.html?id=' + cls.id + '" aria-label="Open class roster" title="Roster and grades">' + icon('users', 16) + '</a>'
                    + modal_button('classModal', icon('edit', 16), 'icon-btn', P.class_fill(cls), 'Edit class ' + cls.key, 'Edit')
                    + confirm_button('section_delete', cls.id, 'Delete class ' + cls.key + '? Its meetings, roster of ' + cls.students + ' students and their grades are deleted too.')
                    + '</div></li>';
            }
            h += '</ul>';
        }
        if (!slotList.length) h += '<p class="empty">No sessions match these filters.</p>';
        h += pagination(page, 'sessions') + '</div>';

        h += '<div class="stack"><div class="card panel"><div class="panel-head"><div><h2>Room use by building</h2><p class="muted small">Rooms with at least one class on ' + e(days.get(day)) + '</p></div></div>'
            + bar_list(utilization, 100, 'pct', 'block', college ? {} : Object.fromEntries(utilization.map(([c]) => [c, url_with({ college: c }) + '#list'])))
            + '</div><div class="card panel"><div class="panel-head"><div><h2>Weekly load</h2><p class="muted small">Class sessions per day</p></div></div>'
            + bar_list(weekly, null, 'number', 'purple', Object.fromEntries(WEEK_DAYS.map(([k, label]) => [label, url_with({ day: k }) + '#list'])))
            + '<p class="note">' + icon('book', 14) + ' ' + offCampus + ' practicum and internship sections are off-campus, with no scheduled meetings.</p></div></div></section>';
        h += P.class_modal();
        return h + page_end();
    }

    /* ================================================================== */
    /* Grade submissions                                                   */
    /* ================================================================== */

    function grades() {
        const user = P.require_login();
        P.require_permission('academic.view', user);
        const college = valid_college(q('college'));
        const status = q('status'), search = q('q'), sort = q('sort', 'progress');

        const scope = filter_rows(P.classes(), { college });
        let rows = search_rows(scope, search, ['key', 'code', 'title', 'section', 'faculty']);
        const statusCounts = count_values(rows.map(r => r.grade));
        statusCounts.pending = (statusCounts['In progress'] || 0) + (statusCounts['Not started'] || 0);
        rows = status === 'pending' ? rows.filter(c => c.grade !== 'Submitted') : filter_rows(rows, { grade: status });
        rows = rows.slice().sort(
            sort === 'faculty' ? (a, b) => cmp(a.faculty, b.faculty)
                : sort === 'code' ? (a, b) => cmp([a.code, a.section], [b.code, b.section])
                    : (a, b) => cmp([a.encoded, a.code], [b.encoded, b.code]));

        maybe_export_csv(rows, [
            ['Code', 'code'], ['Title', 'title'], ['Section', 'section'], ['Faculty', 'faculty'], ['College', 'college'],
            ['Students', 'students'], ['Encoded %', 'encoded'], ['Status', 'grade'], ['Last update', r => (r.updated ? date('Y-m-d H:i', r.updated) : '')],
        ], 'grade-submissions');
        const page = paginate(rows, 20);

        const counts = Object.assign({ Submitted: 0, 'In progress': 0, 'Not started': 0 }, count_values(scope.map(c => c.grade)));
        const total = scope.length;
        const pending = counts['In progress'] + counts['Not started'];
        const deadline = strtotime(current_term().grade_deadline);
        const daysLeft = Math.round((deadline - strtotime(date('Y-m-d', app_now()))) / 86400);
        const studentsWaiting = sum(scope.filter(c => c.grade !== 'Submitted').map(c => c.students));

        const groups = new Map();
        for (const c of scope) {
            const key = college ? year_label(section_year(c.section)) : c.college;
            if (!groups.has(key)) groups.set(key, []);
            groups.get(key).push(c);
        }
        const groupList = Array.from(groups);
        if (college) groupList.sort((a, b) => cmp(a[0], b[0]));

        const byFaculty = bySizeDesc(countBy(scope.filter(c => c.grade !== 'Submitted'), 'faculty'));

        const statusOptions = [['', 'All'], ['pending', 'Pending'], ['In progress', 'In progress'], ['Not started', 'Not started'], ['Submitted', 'Submitted']];
        const chipCounts = Object.assign({ '': (statusCounts.Submitted || 0) + (statusCounts['In progress'] || 0) + (statusCounts['Not started'] || 0) }, statusCounts);
        const hasTerm = current_term().id > 0;

        let h = page_start('grades', 'Grade submissions', user);
        h += page_head('Grade submissions',
            'End-of-term encoding for ' + number_format(total) + ' class sections' + (college ? ' in ' + e(college) : '') + (hasTerm ? ' · Deadline ' + e(date('l, F j', deadline)) : ' · No active term or deadline configured'),
            '<form method="get" class="filters">' + select_filter('college', college_names(), college, 'All colleges') + '</form>' + export_button());

        h += '<section class="grid grid-4">'
            + stat_card('Submitted', number_format(counts.Submitted), total ? pct(counts.Submitted, total) + '% of class sections' : 'No class sections available', 'check-sq', 'green', url_with({ status: 'Submitted' }) + '#list')
            + stat_card('In progress', number_format(counts['In progress']), 'Encoding started', 'clock', 'orange', url_with({ status: 'In progress' }) + '#list')
            + stat_card('Not started', number_format(counts['Not started']), 'No grades encoded yet', 'alert', 'red', url_with({ status: 'Not started' }) + '#list')
            + stat_card('Days to deadline', hasTerm ? String(Math.max(0, daysLeft)) : '—', hasTerm ? number_format(studentsWaiting) + ' students waiting on grades' : 'No active term configured', 'calendar', 'blue')
            + '</section>';

        h += '<section class="grid grid-2-1"><div class="card panel"><div class="panel-head"><div><h2>Progress by ' + (college ? 'year level' : 'college') + '</h2><p class="muted small">Average share of grades encoded per class section</p></div></div>'
            + '<ul class="progress-list progress-list--wide">'
            + groupList.map(([name, list]) => {
                const n = list.length;
                const avg = Math.round(sum(list.map(c => c.encoded)) / Math.max(1, n));
                const sub = list.filter(c => c.grade === 'Submitted').length;
                const done = sub === n;
                return '<li><a href="' + e(college ? '#list' : url_with({ college: name })) + '"><div class="progress-head"><span><strong>' + e(name) + '</strong> <span class="muted xsmall">' + n + ' classes · ' + sub + ' submitted</span></span>'
                    + '<span class="tag tag--sm ' + (done ? 'tag--green' : 'tag--orange') + '">' + (done ? icon('check', 12) + ' Submitted' : (n - sub) + ' pending') + '</span></div>'
                    + '<div class="progress" data-tip="' + e(name + '|' + avg + '% of grades encoded|' + sub + ' of ' + n + ' classes submitted') + '"><span style="--w: ' + avg + '%"></span></div>'
                    + '<div class="progress-foot"><span>' + avg + '% encoded</span><span>' + pct(sub, n) + '% of classes submitted</span></div></a></li>';
            }).join('')
            + '</ul></div><div class="stack"><div class="card panel"><div class="panel-head"><div><h2>Submission rate</h2><p class="muted small">All class sections in scope</p></div></div>'
            + donut_chart([
                ['Submitted', counts.Submitted, 'var(--blue-600)', url_with({ status: 'Submitted' }) + '#list'],
                ['In progress', counts['In progress'], 'var(--orange-500)', url_with({ status: 'In progress' }) + '#list'],
                ['Not started', counts['Not started'], 'var(--slate-200)', url_with({ status: 'Not started' }) + '#list'],
            ], pct(counts.Submitted, total) + '%', 'submitted')
            + '</div><div class="card panel"><div class="panel-head"><div><h2>Most classes pending</h2><p class="muted small">Faculty with unsubmitted grades</p></div></div>'
            + (byFaculty.length
                ? '<ul class="kv">' + byFaculty.slice(0, 5).map(([name, n]) => '<li><a class="link-plain" href="' + e(url_with({ q: name, status: 'pending' }) + '#list') + '">' + e(name) + '</a><strong>' + n + ' class' + (n === 1 ? '' : 'es') + '</strong></li>').join('') + '</ul>'
                : '<p class="empty">Every class has submitted grades.</p>')
            + '</div></div></section>';

        h += '<section class="card panel panel--flush" id="list"><div class="table-toolbar"><div><h2>Class sections</h2><p class="muted small">' + pending + ' of ' + total + ' sections still pending · click a class to encode its grades</p></div>'
            + '<form method="get" class="toolbar-form" action="grades.html#list"><input type="hidden" name="college" value="' + e(college) + '"><input type="hidden" name="status" value="' + e(status) + '">'
            + '<label class="search-field">' + icon('search', 16) + '<input type="search" name="q" value="' + e(search) + '" placeholder="Class code, title or faculty"></label>'
            + select_filter('sort', [['code', 'Sort: class code'], ['faculty', 'Sort: faculty']], sort === 'progress' ? '' : sort, 'Sort: least encoded') + '</form></div>'
            + '<div class="toolbar-chips">' + chip_links('status', statusOptions, status, chipCounts) + '</div>'
            + '<div class="table-wrap"><table class="data data--rows"><thead><tr><th>Class</th><th>Section</th><th>Faculty</th><th class="num">Students</th><th>Encoded</th><th>Status</th><th>Last update</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody>';
        for (const c of page.items) {
            h += '<tr data-href="class.html?id=' + c.id + '" class="is-link"><td class="col-wide"><div class="cell-stack"><strong>' + e(c.title) + '</strong><span>' + e(c.code) + ' · ' + e(c.college) + '</span></div></td>'
                + '<td class="nowrap">' + e(c.section) + '</td><td class="nowrap">' + e(c.faculty) + '</td><td class="num">' + c.students + '</td>'
                + '<td class="nowrap"><div class="mini-bar mini-bar--' + e(status_tone(c.grade)) + '"><span style="--w: ' + c.encoded + '%"></span></div><span class="mini-bar-num">' + c.encoded + '%</span></td>'
                + '<td>' + tag(c.grade) + '</td><td class="nowrap muted">' + e(rel_time(c.updated)) + '</td>'
                + '<td><a class="btn btn--ghost btn--xs" href="class.html?id=' + c.id + '">' + icon('edit', 14) + ' ' + (c.grade === 'Submitted' ? 'View' : 'Encode') + '</a></td></tr>';
        }
        if (!page.items.length) h += '<tr><td colspan="8" class="empty">No class sections match these filters.</td></tr>';
        h += '</tbody></table></div>' + pagination(page, 'class sections') + '</section>';
        return h + page_end();
    }

    /* ================================================================== */
    /* One class: roster and grades                                        */
    /* ================================================================== */

    function classPage() {
        const user = P.require_login();
        P.require_permission('academic.view', user);
        const cls = P.class_by_id(parseInt(q('id'), 10) || 0);
        if (!cls) {
            P.flash('error', 'That class could not be found. It may have been deleted.');
            location.replace('grades.html');
            throw new P.Halt();
        }
        const roster = P.db.all('enrollments').filter(en => en.section_id === cls.id)
            .map(en => Object.assign({ en }, P.db.get('students', en.student_id)))
            .filter(r => r.id !== undefined)
            .sort((a, b) => cmpStr(a.last_name, b.last_name) || cmpStr(a.first_name, b.first_name) || cmpStr(a.middle_name, b.middle_name));
        const submitted = cls.grade === 'Submitted';
        const locked = submitted || !can('academic.manage') || is_historical_term();
        const encoded = roster.filter(r => r.en.final_grade !== null).length;

        maybe_export_csv(roster, [
            ['Student no.', 'student_no'], ['Name', r => r.last_name + ', ' + r.first_name + (r.middle_name ? ' ' + r.middle_name : '')],
            ['Midterm', r => r.en.midterm_grade || ''], ['Final', r => r.en.final_grade || ''],
        ], 'grades-' + cls.key.replace(/[^A-Za-z0-9]+/g, '-').toLowerCase());

        const buckets = [['1.00–1.50', 0], ['1.75–2.25', 0], ['2.50–3.00', 0], ['5.00 (failed)', 0], ['INC', 0], ['DRP', 0], ['Not encoded', 0]];
        const bump = k => { buckets.find(b => b[0] === k)[1]++; };
        const numeric = [];
        for (const r of roster) {
            const g = r.en.final_grade;
            if (g === null) bump('Not encoded');
            else if (g === 'INC' || g === 'DRP') bump(g);
            else if (g === '5.00') bump('5.00 (failed)');
            else if (parseFloat(g) <= 1.5) bump('1.00–1.50');
            else if (parseFloat(g) <= 2.25) bump('1.75–2.25');
            else bump('2.50–3.00');
            if (g !== null && !isNaN(Number(g))) numeric.push(parseFloat(g));
        }
        const passed = numeric.filter(g => g <= 3.0).length;
        const gradeSelect = (name, current) => '<select class="f-input f-input--grade" name="' + e(name) + '"' + (locked ? ' disabled' : '') + ' aria-label="Grade"><option value="">—</option>'
            + GRADE_VALUES.map(g => '<option' + (g === current ? ' selected' : '') + '>' + g + '</option>').join('') + '</select>';
        const self = 'class.html?id=' + cls.id;
        const tone = status_tone(cls.grade);

        let h = page_start('grades', cls.key, user);
        h += '<section class="page-head page-head--detail"><div><a class="crumb" href="grades.html">' + icon('chev-l', 14) + ' Grade submissions</a>'
            + '<h1>' + e(cls.title) + '</h1><p class="muted">' + e(cls.code) + ' · ' + e(cls.section) + ' · ' + e(cls.college) + ' · ' + cls.units + ' units · ' + e(current_term().name) + '</p></div>'
            + '<div class="filters">' + export_button()
            + modal_button('classModal', icon('edit', 16) + ' Edit class', 'btn btn--ghost btn--sm', P.class_fill(cls), 'Edit class ' + cls.key)
            + confirm_button('section_delete', cls.id, 'Delete class ' + cls.key + '? Its meetings, roster of ' + roster.length + ' students and their grades are deleted too.',
                icon('trash', 16) + ' Delete class', 'btn btn--ghost btn--sm btn--ghost-danger', {}, 'schedule.html', 'Delete class')
            + '</div></section>';

        h += '<section class="grid grid-4">'
            + stat_card('Students', String(roster.length), 'On the class roster', 'users', 'blue')
            + stat_card('Final grades encoded', encoded + ' / ' + roster.length, pct(encoded, roster.length) + '% complete', 'check-sq', encoded === roster.length && roster.length ? 'green' : 'orange')
            + stat_card('Class average', numeric.length ? number_format(sum(numeric) / numeric.length, 2) : '—', numeric.length ? pct(passed, numeric.length) + '% passing' : 'No numeric grades yet', 'star', 'purple')
            + stat_card('Status', cls.grade, cls.updated ? 'Updated ' + e(rel_time(cls.updated)) : 'No grades saved yet', 'clock', tone === 'green' ? 'green' : (tone === 'red' ? 'red' : 'orange'))
            + '</section>';

        h += '<section class="grid grid-2-1"><div class="card panel panel--flush" id="list"><form method="post" id="gradesForm" data-action-form>'
            + '<input type="hidden" name="action" value="grades_save"><input type="hidden" name="id" value="' + cls.id + '"><input type="hidden" name="return" value="' + e(self) + '#list">'
            + '<div class="table-toolbar"><div><h2>Roster and grades</h2><p class="muted small">' + (submitted ? 'Final grades are submitted and locked. Reopen to change them.' : 'Scale 1.00 (highest) to 3.00 (passing) · 5.00 failed · INC incomplete · DRP dropped') + '</p></div>'
            + '<div class="toolbar-form">';
        if (!can('academic.manage') || is_historical_term()) h += '<span class="muted small">Read-only access</span>';
        else if (submitted) h += tag('Submitted') + '<button type="submit" name="do" value="reopen" class="btn btn--ghost btn--sm">' + icon('edit', 16) + ' Reopen for editing</button>';
        else {
            h += '<span class="unsaved" id="unsaved" hidden>' + icon('alert', 14) + ' Unsaved changes</span>'
                + '<button type="submit" name="do" value="save" class="btn btn--ghost btn--sm" ' + (roster.length ? '' : 'disabled') + '>Save draft</button>'
                + '<button type="submit" name="do" value="submit" class="btn btn--primary btn--sm" ' + (roster.length ? '' : 'disabled') + '>' + icon('check', 16) + ' Submit final grades</button>';
        }
        h += '</div></div><div class="table-wrap"><table class="data data--rows"><thead><tr><th>#</th><th>Student</th><th>Status</th><th>Midterm</th><th>Final</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody>';
        roster.forEach((r, i) => {
            const name = P.person_name(r.first_name, r.middle_name, r.last_name);
            h += '<tr><td class="muted">' + (i + 1) + '</td><td><div class="cell-person">' + avatar(name) + '<div><strong>' + e(r.last_name + ', ' + r.first_name) + '</strong>'
                + '<span><a class="link-plain" href="enrollment.html?' + e(qs({ q: r.student_no })) + '#list">' + e(r.student_no) + '</a></span></div></div></td>'
                + '<td>' + tag(r.status) + '</td><td>' + gradeSelect('mid[' + r.id + ']', r.en.midterm_grade) + '</td><td>' + gradeSelect('fin[' + r.id + ']', r.en.final_grade) + '</td>'
                + '<td><div class="row-actions">' + confirm_button('enrollment_remove', cls.id, 'Remove ' + name + ' from ' + cls.key + '? Their grades in this class are deleted.', '', 'icon-btn icon-btn--danger', { student: r.id }, self + '#list', 'Remove from class') + '</div></td></tr>';
        });
        if (!roster.length) h += '<tr><td colspan="6" class="empty">No students are enrolled in this class yet. Add one with the form on the right.</td></tr>';
        h += '</tbody></table></div></form></div>';

        h += '<div class="stack"><div class="card panel"><div class="panel-head"><div><h2>Class details</h2><p class="muted small">' + e(cls.kind) + ' · ' + e(cls.mode) + '</p></div>' + tag(cls.mode) + '</div>'
            + '<ul class="kv kv--flush"><li><span>Faculty</span><strong>' + e(cls.faculty) + '</strong></li><li><span>Schedule</span><strong>' + e(cls.schedule) + '</strong></li>'
            + '<li><span>' + (cls.mode === 'Online' ? 'Platform' : 'Room') + '</span><strong>' + e(cls.room) + '</strong></li></ul></div>'
            + '<div class="card panel"><div class="panel-head"><div><h2>Final grade distribution</h2><p class="muted small">Number of students in each range</p></div></div>' + bar_list(buckets, null, 'number', 'purple') + '</div>';
        if (can('academic.manage') && !is_historical_term()) {
            h += '<div class="card panel"><div class="panel-head"><div><h2>Add a student</h2><p class="muted small">For irregular students taking this class</p></div></div>'
                + '<form method="post" class="inline-form" data-action-form><input type="hidden" name="action" value="enrollment_add"><input type="hidden" name="id" value="' + cls.id + '"><input type="hidden" name="return" value="' + e(self) + '#list">'
                + '<input class="f-input" name="student_no" placeholder="Student no., e.g. 2023-10482" pattern="\\d{4}-\\d{5}" maxlength="12" required aria-label="Student number">'
                + '<button type="submit" class="btn btn--primary btn--sm">' + icon('plus', 16) + ' Add</button></form></div>';
        }
        h += '</div></section>' + P.class_modal();
        return h + page_end();
    }

    /* ================================================================== */
    /* At-risk students                                                    */
    /* ================================================================== */

    function atRisk() {
        const user = P.require_login();
        P.require_permission('academic.view', user);
        const college = valid_college(q('college'));
        const type = q('type'), priority = q('priority'), intervention = q('intervention'), search = q('q');
        const hist = is_historical_term();

        const scope = filter_rows(P.at_risk(), { college });
        let rows = filter_rows(scope, { priority, intervention });
        rows = search_rows(rows, search, ['name', 'id', 'program', 'section', 'adviser']);
        const typeCounts = count_values(rows.map(r => r.type));
        rows = filter_rows(rows, { type });

        maybe_export_csv(rows, [
            ['Student no.', 'id'], ['Name', 'name'], ['Program', 'program_name'], ['Section', 'section'], ['College', 'college'],
            ['Risk', 'type'], ['Detail', 'detail'], ['Priority', 'priority'], ['Adviser', 'adviser'],
            ['Intervention', 'intervention'], ['Last contact', r => (r.last_contact ? date('Y-m-d', r.last_contact) : '')],
        ], 'at-risk-students');
        const page = paginate(rows, 20);

        const total = scope.length;
        const byType = Object.assign(Object.fromEntries(RISK_TYPES.map(t => [t, 0])), count_values(scope.map(r => r.type)));
        const urgent = scope.filter(r => r.priority === 'Urgent').length;
        const notContacted = scope.filter(r => r.intervention === 'Not contacted').length;
        const urgentNotContacted = scope.filter(r => r.priority === 'Urgent' && r.intervention === 'Not contacted').length;

        const mix = new Map();
        for (const r of scope) {
            const key = college ? r.program : r.college;
            if (!mix.has(key)) mix.set(key, Object.fromEntries(RISK_TYPES.map(t => [t, 0])));
            mix.get(key)[r.type]++;
        }
        const mixSum = m => sum(Object.values(m));
        const mixList = Array.from(mix).sort((a, b) => mixSum(b[1]) - mixSum(a[1]));
        const mixMax = mixList.length ? Math.max(...mixList.map(([, m]) => mixSum(m))) : 1;

        const studentsPer = {};
        for (const s of P.students()) {
            if (!college || s.college === college) { const key = college ? s.program : s.college; studentsPer[key] = (studentsPer[key] || 0) + 1; }
        }
        const ivCounts = count_values(scope.map(r => r.intervention));
        const interventions = INTERVENTION_STATUSES.map(s => [s, ivCounts[s] || 0]);
        for (const k of Object.keys(ivCounts)) if (!INTERVENTION_STATUSES.includes(k)) interventions.push([k, ivCounts[k]]);
        const trend = P.risk_history(college);
        const trendFirst = trend.length ? trend[0][1] : 0;

        const typeColors = { Failing: 'var(--red-500)', INC: 'var(--orange-500)', 'Behind curriculum': 'var(--purple-500)' };
        const typeOptions = [['', 'All']].concat(RISK_TYPES.map(t => [t, t]));
        const chipCounts = Object.assign({ '': sum(Object.values(typeCounts)) }, typeCounts);

        let h = page_start('risk', 'At-risk students', user);
        h += page_head('At-risk students',
            (P.grade_evidence_count(college) === 0 && total === 0 ? 'Risk assessment unavailable: no grade or backlog evidence. ' : '') + number_format(total) + ' observed cases' + (college ? ' in ' + e(college) : ' across all colleges') + ' who may need academic support',
            '<form method="get" class="filters">' + select_filter('college', college_names(), college, 'All colleges') + '</form>' + export_button());

        h += '<section class="grid grid-4">'
            + stat_card('Urgent cases', number_format(urgent), urgentNotContacted + ' not yet contacted', 'alert', 'red', url_with({ priority: 'Urgent' }) + '#list')
            + stat_card('Failing', number_format(byType.Failing), 'One or more failing subjects', 'trend-up', 'orange', url_with({ type: 'Failing' }) + '#list')
            + stat_card('Incomplete (INC)', number_format(byType.INC), 'Missing course requirements', 'file', 'blue', url_with({ type: 'INC' }) + '#list')
            + stat_card('Behind curriculum', number_format(byType['Behind curriculum']), 'Subjects left to complete', 'book', 'purple', url_with({ type: 'Behind curriculum' }) + '#list')
            + '</section>';

        h += '<section class="grid grid-2-1"><div class="card panel"><div class="panel-head"><div><h2>Risk by ' + (college ? 'program' : 'college') + '</h2><p class="muted small">Number of at-risk students and their share of enrollment</p></div>'
            + '<div class="legend legend--row">' + Object.keys(typeColors).map(label => '<button type="button" class="legend-toggle" data-series-toggle="' + e(label) + '" aria-pressed="true" title="Click to hide or show"><i class="swatch" style="background: ' + typeColors[label] + '"></i>' + e(label) + '</button>').join('')
            + '</div></div><ul class="stacked" data-stacked>'
            + mixList.map(([name, counts]) => {
                const s = mixSum(counts);
                return '<li><a href="' + e((college ? url_with({ q: name }) : url_with({ college: name })) + '#list') + '"><span class="stacked-label">' + e(name) + '</span>'
                    + '<span class="stacked-track" style="--w: ' + round(s / mixMax * 100, 1) + '%">'
                    + RISK_TYPES.filter(t => counts[t]).map(t => '<span style="flex: ' + counts[t] + '; background: ' + typeColors[t] + '" data-series="' + e(t) + '" data-tip="' + e(name + '|' + t + ': ' + counts[t] + ' student' + (counts[t] === 1 ? '' : 's') + ' · ' + pct(counts[t], s) + '% of this group') + '"></span>').join('')
                    + '</span><span class="stacked-num"><strong>' + s + '</strong> <em>' + pct(s, studentsPer[name] || 0, 1) + '%</em></span></a></li>';
            }).join('')
            + '</ul></div><div class="stack"><div class="card panel"><div class="panel-head"><div><h2>Monthly trend</h2><p class="muted small">At-risk students this semester</p></div>'
            + '<span class="pill pill--green">' + icon('arrow-dn', 12) + ' ' + pct(trendFirst - total, trendFirst, 1) + '%</span></div>' + line_chart(trend, 'var(--red-500)', 120)
            + '</div><div class="card panel"><div class="panel-head"><div><h2>Intervention status</h2><p class="muted small">' + (hist ? 'Historical intervention records are not archived by term' : notContacted + ' students not yet contacted') + '</p></div></div>'
            + bar_list(interventions, null, 'number', 'orange block', Object.fromEntries(INTERVENTION_STATUSES.map(s => [s, url_with({ intervention: s }) + '#list'])))
            + '</div></div></section>';

        h += '<section class="card panel panel--flush" id="list"><div class="table-toolbar"><div><h2>Students needing support</h2><p class="muted small">Urgent cases first</p></div>'
            + '<form method="get" class="toolbar-form" action="at-risk.html#list"><input type="hidden" name="college" value="' + e(college) + '"><input type="hidden" name="type" value="' + e(type) + '">'
            + '<label class="search-field">' + icon('search', 16) + '<input type="search" name="q" value="' + e(search) + '" placeholder="Student, section or adviser"></label>'
            + select_filter('priority', ['Urgent', 'Monitor'], priority, 'All priorities') + select_filter('intervention', INTERVENTION_STATUSES, intervention, 'All interventions') + '</form></div>'
            + '<div class="toolbar-chips">' + chip_links('type', typeOptions, type, chipCounts) + '</div>'
            + '<div class="table-wrap"><table class="data data--rows"><thead><tr><th>Student</th><th>Program</th><th>Risk</th><th>Priority</th><th>Adviser</th><th>Intervention</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody>';
        for (const r of page.items) {
            const logged = r.intervention !== 'Not contacted';
            h += '<tr><td><div class="cell-person">' + avatar(r.name, 'slate') + '<div><strong>' + e(r.name) + '</strong><span>' + e(r.id) + '</span></div></div></td>'
                + '<td><div class="cell-stack"><strong>' + e(r.section) + '</strong><span>' + e(r.college) + '</span></div></td>'
                + '<td class="nowrap"><div class="cell-stack"><strong class="text-' + e(status_tone(r.type)) + '">' + e(r.type) + '</strong><span>' + e(r.detail) + '</span></div></td>'
                + '<td>' + tag(r.priority) + '</td><td class="nowrap">' + e(r.adviser) + '</td>'
                + '<td><div class="cell-stack">' + tag(r.intervention) + '<span>' + (hist ? 'No archived contact date' : 'Last contact: ' + e(days_ago(r.last_contact))) + '</span></div></td>'
                + '<td><div class="row-actions">'
                + modal_button('interventionModal', icon('edit', 16), 'icon-btn', {
                    id: r.sid, name: r.name + ' · ' + r.id, detail: r.type + ' — ' + r.detail, status: logged ? r.intervention : 'Contacted',
                    last_contact: date('Y-m-d', r.last_contact || app_now()), notes: r.notes || '',
                }, logged ? 'Edit intervention' : 'Log intervention', logged ? 'Edit intervention' : 'Log intervention')
                + (logged ? confirm_button('intervention_delete', r.sid, 'Delete the intervention record for ' + r.name + '? They go back to “Not contacted”.', '', 'icon-btn icon-btn--danger', {}, '', 'Delete intervention') : '')
                + '</div></td></tr>';
        }
        if (!page.items.length) h += '<tr><td colspan="7" class="empty">No students match these filters.</td></tr>';
        h += '</tbody></table></div>' + pagination(page, 'students') + '</section>' + P.intervention_modal();
        return h + page_end();
    }

    /* ================================================================== */
    /* Faculty evaluations                                                 */
    /* ================================================================== */

    const RATING_BANDS = [['Excellent', 4.5, 5.01], ['Very good', 4.0, 4.5], ['Good', 3.5, 4.0], ['Needs support', 0, 3.5]];
    const NOT_EVALUATED = 'Not evaluated';
    function rating_band(rating) {
        for (const [band, lo, hi] of RATING_BANDS) if (rating >= lo && rating < hi) return band;
        return 'Needs support';
    }

    function evaluations() {
        const user = P.require_login();
        P.require_permission('sete.view', user);
        const college = valid_college(q('college'));
        const rank = q('rank'), band = q('band'), search = q('q'), sort = q('sort');
        const stripTitle = n => n.replace(/^\S+\s/, '');

        const everyone = filter_rows(P.faculty(), { college }).map(f => Object.assign({}, f, { band: f.responses ? rating_band(f.rating) : NOT_EVALUATED }));
        const scope = everyone.filter(f => f.responses > 0);
        let rows = filter_rows(everyone, { rank });
        rows = search_rows(rows, search, ['name', 'college', 'rank']);
        const bandCounts = count_values(rows.map(r => r.band));
        rows = filter_rows(rows, { band }).slice().sort(
            sort === 'responses' ? (a, b) => cmp([b.responses, b.rating], [a.responses, a.rating])
                : sort === 'change' ? (a, b) => cmp(b.rating - b.previous, a.rating - a.previous)
                    : sort === 'name' ? (a, b) => cmp(stripTitle(a.name), stripTitle(b.name))
                        : (a, b) => cmp([b.rating, b.responses], [a.rating, a.responses]));

        maybe_export_csv(rows, [
            ['Faculty', 'name'], ['College', 'college'], ['Rank', 'rank'], ['Responses', 'responses'],
            ['Response rate %', r => pct(r.responses, r.enrolled)], ['Rating', 'rating'], ['Previous term', 'previous'],
        ].concat(EVAL_LABELS.map(cat => [cat, r => r.categories[cat]])), 'faculty-evaluations');
        const page = paginate(rows, 20);

        const total = scope.length;
        const responses = sum(scope.map(f => f.responses));
        const avg = responses ? sum(scope.map(f => f.rating * f.responses)) / responses : 0;
        const historyScope = scope.filter(f => f.has_previous);
        const prevAvg = historyScope.length ? sum(historyScope.map(f => f.previous)) / historyScope.length : null;
        const enrolled = sum(everyone.map(f => f.enrolled));
        const categoryAvg = EVAL_LABELS.map(cat => [cat, responses ? sum(scope.map(f => f.categories[cat] * f.responses)) / responses : 0]);
        const bandTally = count_values(scope.map(f => f.band));
        const bands = RATING_BANDS.map(([b]) => [b, bandTally[b] || 0]);

        const invitedByGroup = {};
        for (const f of everyone) { const key = college ? f.rank : f.college; invitedByGroup[key] = (invitedByGroup[key] || 0) + f.enrolled; }
        const byGroup = new Map();
        for (const f of scope) { const key = college ? f.rank : f.college; if (!byGroup.has(key)) byGroup.set(key, []); byGroup.get(key).push(f); }
        const meanRating = list => sum(list.map(f => f.rating)) / list.length;
        const groupList = Array.from(byGroup).sort((a, b) => meanRating(b[1]) - meanRating(a[1]));

        const bandOptions = [['', 'All']].concat(RATING_BANDS.map(([b]) => [b, b]), [[NOT_EVALUATED, NOT_EVALUATED]]);
        const bandLinks = Object.fromEntries(RATING_BANDS.map(([b]) => [b, url_with({ band: b }) + '#list']));
        const chipCounts = Object.assign({ '': sum(Object.values(bandCounts)) }, bandCounts);

        let h = page_start('evaluations', 'Faculty evaluations', user);
        h += page_head('Faculty evaluations', 'Anonymous student feedback · ' + e(current_term().name) + (college ? ' · ' + e(college) : ''),
            '<form method="get" class="filters">' + select_filter('college', college_names(), college, 'All colleges') + '</form>' + export_button()
            + modal_button('facultyModal', icon('plus', 16) + ' Add faculty'));

        h += '<section class="card panel"><p class="muted small">Interpret SETE scores alongside response counts, participation, course context, peer review and other evidence. Ratings alone must not determine personnel decisions. Student outcome predictions do not use SETE data.</p></section>';
        h += '<section class="grid grid-4">'
            + stat_card('Faculty evaluated', number_format(total), college ? e(college) : 'Across ' + colleges().size + ' colleges', 'users', 'blue')
            + stat_card('Average rating', responses ? number_format(avg, 2) : '—', responses ? (prevAvg === null ? 'No prior-term baseline' : 'Prior-term average: ' + number_format(prevAvg, 2)) : 'No evaluation responses yet', 'star', 'orange')
            + stat_card('Response rate', enrolled ? pct(responses, enrolled) + '%' : '—', number_format(enrolled) + ' students invited', 'check-sq', 'green')
            + stat_card('Total responses', number_format(responses), 'Individual answers stay private', 'shield', 'purple')
            + '</section>';

        h += '<section class="grid grid-2-1"><div class="card panel"><div class="panel-head"><div><h2>Ratings by ' + (college ? 'academic rank' : 'college') + '</h2><p class="muted small">Response-weighted overall rating out of 5</p></div></div>'
            + '<div class="table-wrap"><table class="data"><thead><tr><th>' + (college ? 'Rank' : 'College') + '</th><th class="num">Evaluated faculty</th><th class="num">Responses</th><th class="num">Response rate</th><th>Average rating</th></tr></thead><tbody>'
            + groupList.map(([name, list]) => {
                const resp = sum(list.map(f => f.responses));
                const a = sum(list.map(f => f.rating * f.responses)) / Math.max(1, resp);
                return '<tr><td><a class="link-plain" href="' + e(url_with(college ? { rank: name } : { college: name }) + '#list') + '"><strong>' + e(name) + '</strong></a></td>'
                    + '<td class="num">' + list.length + '</td><td class="num">' + number_format(resp) + '</td><td class="num">' + pct(resp, invitedByGroup[name] || 0) + '%</td>'
                    + '<td><div class="cell-rating"><strong>' + number_format(a, 2) + '</strong>' + stars(round(a, 4)) + '</div></td></tr>';
            }).join('')
            + '</tbody></table></div></div><div class="stack"><div class="card panel"><div class="panel-head"><div><h2>By category</h2><p class="muted small">Response-weighted score out of 5</p></div></div>'
            + (responses ? bar_list(categoryAvg, 5, 'rating', 'orange block') : '<p class="empty">No evaluation responses recorded.</p>')
            + '</div><div class="card panel"><div class="panel-head"><div><h2>Rating bands</h2><p class="muted small">Number of faculty in each band</p></div></div>'
            + bar_list(bands, null, 'number', 'purple', bandLinks) + '</div></div></section>';

        h += '<section class="card panel panel--flush" id="list"><div class="table-toolbar"><div><h2>Faculty results</h2><p class="muted small">Everyone on the faculty list. Only totals and averages are shown; student responses stay anonymous.</p></div>'
            + '<form method="get" class="toolbar-form" action="evaluations.html#list"><input type="hidden" name="college" value="' + e(college) + '"><input type="hidden" name="band" value="' + e(band) + '">'
            + '<label class="search-field">' + icon('search', 16) + '<input type="search" name="q" value="' + e(search) + '" placeholder="Faculty name"></label>'
            + select_filter('rank', P.RANKS, rank, 'All ranks')
            + select_filter('sort', [['responses', 'Sort: most responses'], ['change', 'Sort: most improved'], ['name', 'Sort: name']], sort, 'Sort: highest rated') + '</form></div>'
            + '<div class="toolbar-chips">' + chip_links('band', bandOptions, band, chipCounts) + '</div>'
            + '<div class="table-wrap"><table class="data data--rows"><thead><tr><th>Faculty member</th><th>College</th><th class="num">Responses</th><th>Rating</th><th>Change</th><th>Weakest area</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody>';
        for (const f of page.items) {
            const change = round(f.rating - f.previous, 1);
            const minScore = Math.min(...EVAL_LABELS.map(c => f.categories[c]));
            const weak = EVAL_LABELS.find(c => f.categories[c] === minScore);
            h += '<tr><td><div class="cell-person">' + avatar(f.name) + '<div><strong>' + e(f.name) + '</strong><span>' + e(f.rank) + '</span></div></div></td>'
                + '<td class="nowrap">' + e(f.college) + '</td>'
                + '<td class="num"><div class="cell-stack cell-stack--end"><strong>' + f.responses + '</strong><span>' + pct(f.responses, f.enrolled) + '% responded</span></div></td>';
            if (f.responses > 0) {
                h += '<td><div class="cell-rating"><strong>' + number_format(f.rating, 1) + '</strong>' + stars(f.rating) + '</div></td>'
                    + '<td class="nowrap"><span class="delta ' + (change > 0 ? 'up' : (change < 0 ? 'down' : '')) + '">' + (change > 0 ? '▲ ' : (change < 0 ? '▼ ' : ''))
                    + (!f.has_previous ? 'No prior-term data' : (change === 0 ? 'No change' : number_format(Math.abs(change), 1))) + '</span></td>'
                    + '<td><div class="cell-stack"><strong>' + e(weak) + '</strong><span>' + number_format(f.categories[weak], 1) + ' / 5</span></div></td>';
            } else {
                h += '<td colspan="3">' + tag(NOT_EVALUATED, 'slate') + ' <span class="muted xsmall">' + (f.sections ? f.sections + ' class' + (f.sections === 1 ? '' : 'es') + ', no responses yet' : 'No classes this term') + '</span></td>';
            }
            h += '<td>' + row_actions('facultyModal', {
                id: f.fid, title: f.title, first_name: f.first, last_name: f.last, college: f.college, rank: f.rank, employment: f.employment, email: f.email,
            }, 'Edit ' + f.name, 'faculty_delete', f.fid, 'Delete ' + f.name + '?' + (f.sections ? ' Their ' + f.sections + ' class' + (f.sections === 1 ? '' : 'es') + ' will be left without a teacher (TBA).' : '') + ' This can’t be undone.') + '</td></tr>';
        }
        if (!page.items.length) h += '<tr><td colspan="7" class="empty">No faculty match these filters.</td></tr>';
        h += '</tbody></table></div>' + pagination(page, 'faculty') + '</section>' + P.faculty_modal();
        return h + page_end();
    }

    /* ================================================================== */
    /* Outcome prediction                                                  */
    /* ================================================================== */

    function predictions() {
        const user = P.require_login();
        P.require_permission('prediction.demo', user);
        const m = P.model();
        if (!m.tree || !m.metrics || !m.samples) {
            return page_start('predictions', 'Outcome prediction', user) + '<section class="card panel"><h1>Demonstration model unavailable</h1><p>Please ask an administrator to verify the trained model artifacts.</p></section>' + page_end();
        }
        const { tree, metrics, samples } = m;
        const values = samples[0].features;

        let h = page_start('predictions', 'Outcome prediction', user);
        h += page_head('Student outcome prediction', 'Demonstration predictions using public UCI 697 data. No university student records are used or stored.');
        h += '<section class="card panel"><h2>Demonstration only</h2><p class="muted">The model predicts eventual Dropout, Enrolled, or Graduate outcomes after the first semester. Its results support advising discussions. Local university performance has not been validated. SETE ratings and faculty personnel decisions are outside this model.</p></section>';
        h += '<div class="grid grid-2"><section class="card panel"><h2>First-semester academic inputs</h2>'
            + '<p class="muted small">Units mean curricular units (subjects), not credit hours. Evaluations mean assessment occurrences in the source dataset, not teaching evaluations. Average grade uses the source 0–20 scale; 0 can represent no earned grade. Do not enter a Philippine 1–5 GWA directly.</p>'
            + '<label class="f"><span class="f-label">Load an external demonstration record</span><select class="f-input" id="demoSample">'
            + samples.map((s, i) => '<option value="' + i + '">' + e(s.id) + '</option>').join('') + '</select></label>'
            + '<form id="predictionForm" class="f-grid" novalidate>'
            + tree.features.map(f => P.field(f.label, P.input('features[' + f.key + ']', { type: 'number', required: true, min: f.min, max: f.max, step: f.integer ? '1' : 'any', value: values[f.key] === undefined ? '' : values[f.key], 'data-feature': f.key }), 'UCI observed range: ' + f.min + '–' + f.max, 3)).join('')
            + '<div class="f f--3"><button type="submit" class="btn btn--primary">Predict demonstration outcome</button></div></form>'
            + '<p id="predictionError" class="tone-red" role="alert"></p></section>'
            + '<section class="card panel" aria-live="polite" aria-atomic="true"><h2>Demonstration prediction</h2><div id="predictionResult" hidden>'
            + '<p class="muted small">Predicted outcome</p><h3 id="predictedOutcome"></h3><p id="predictedIndicator"></p><p id="predictedRecommendation"></p>'
            + '<h3>Uncalibrated class shares</h3><ul id="predictedShares"></ul>'
            + '<p class="muted small">Tree leaf shares are not validated individual risk probabilities. Predictions never create interventions, edit student grades, or change faculty evaluations.</p></div>'
            + '<p id="predictionPlaceholder" class="muted">Select a public demonstration record or enter four source-compatible values, then run the prediction.</p></section></div>';
        const bp = metrics.method.best_parameters;
        h += '<section class="card panel"><div class="panel-head"><div><h2>Held-out test evaluation</h2><p class="muted small">Official UCI data · ' + metrics.method.train_records + ' training / ' + metrics.method.test_records + ' test records · Five-fold stratified training cross-validation</p></div>'
            + '<a class="link-strong" href="https://archive.ics.uci.edu/dataset/697/predict+students+dropout+and+academic+success" target="_blank" rel="noopener">Dataset source</a></div>'
            + '<div class="grid grid-3">'
            + stat_card('Dropout recall', number_format(metrics.test.dropout_recall * 100, 2) + '%', 'Eventual dropouts identified in test set', 'alert', 'orange')
            + stat_card('Macro F1', number_format(metrics.test.macro_f1, 4), 'Equal weight to all three outcome classes', 'chart', 'blue')
            + stat_card('Tree depth', String(metrics.tree.depth), metrics.tree.leaves + ' terminal leaves', 'grid', 'purple')
            + '</div><h3>Confusion matrix</h3><p class="muted small">Rows are actual outcomes; columns are predicted outcomes. Counts refer to external test records.</p>'
            + '<div class="table-wrap"><table class="data"><thead><tr><th>Actual / Predicted</th>' + metrics.confusion_matrix_labels.map(l => '<th>' + e(l) + '</th>').join('') + '</tr></thead><tbody>'
            + metrics.test.confusion_matrix.map((row, i) => '<tr><th>' + e(metrics.confusion_matrix_labels[i]) + '</th>' + row.map(n => '<td>' + n + '</td>').join('') + '</tr>').join('')
            + '</tbody></table></div><p class="muted small">Selected tree: maximum depth ' + e(bp.max_depth) + '; minimum leaf records ' + e(bp.min_samples_leaf) + '; class weighting ' + e(bp.class_weight === null || bp.class_weight === undefined ? 'none' : bp.class_weight) + '. Features include no second-semester information or SETE data.</p></section>';
        return h + page_end();
    }

    function bindPredictions() {
        const form = document.getElementById('predictionForm');
        if (!form) return;
        const samples = P.model().samples;
        const error = document.getElementById('predictionError');
        const result = document.getElementById('predictionResult');
        const placeholder = document.getElementById('predictionPlaceholder');
        const invalidate = () => { result.hidden = true; placeholder.hidden = false; error.textContent = ''; };
        document.getElementById('demoSample').addEventListener('change', ev => {
            const sample = samples[Number(ev.target.value)];
            if (!sample) return;
            form.querySelectorAll('[data-feature]').forEach(inputEl => { inputEl.value = sample.features[inputEl.dataset.feature]; });
            invalidate();
        });
        form.addEventListener('input', invalidate);
        form.addEventListener('submit', ev => {
            ev.preventDefault();
            invalidate();
            const features = {};
            let numeric = true;
            form.querySelectorAll('[data-feature]').forEach(inputEl => {
                if (inputEl.value.trim() === '' || isNaN(Number(inputEl.value))) numeric = false;
                features[inputEl.dataset.feature] = Number(inputEl.value);
            });
            try {
                if (!numeric) throw new P.InputError('All four inputs must be numeric.');
                const data = P.predict_outcome(features);
                document.getElementById('predictedOutcome').textContent = data.outcome;
                document.getElementById('predictedIndicator').textContent = data.indicator;
                document.getElementById('predictedRecommendation').textContent = data.recommendation;
                const shares = document.getElementById('predictedShares');
                shares.replaceChildren();
                Object.entries(data.probabilities).forEach(([label, share]) => {
                    const item = document.createElement('li');
                    item.textContent = label + ': ' + (share * 100).toFixed(1) + '%';
                    shares.append(item);
                });
                result.hidden = false;
                placeholder.hidden = true;
            } catch (failure) {
                error.textContent = failure instanceof P.InputError ? failure.message : 'Prediction unavailable. Please reload and try again.';
            }
        });
    }

    /* ================================================================== */
    /* Reference setup                                                     */
    /* ================================================================== */

    function setup() {
        const user = P.require_login();
        P.require_permission('academic.manage', user);
        let h = page_start('setup', 'Reference setup', user);
        h += page_head('Reference setup', 'Enter official university reference information. No demonstration records are created.');
        if (is_historical_term()) {
            return h + '<section class="card panel"><p>Historical terms are read-only. Select the current semester before updating reference catalogs.</p></section>' + page_end();
        }
        const collegeOptions = P.db.all('colleges').sort((a, b) => cmpStr(a.name, b.name)).map(c => [String(c.id), c.name]);
        h += '<section class="card panel"><p class="muted">Start with a college, then programs, courses and rooms. Add a current term before scheduling classes or enrolling students. These reference records do not create user accounts. Existing references are preserved.</p></section><div class="grid grid-2">';
        for (const kind of ['college', 'program', 'course', 'room', 'term']) {
            const needsCollege = ['program', 'course', 'room'].includes(kind);
            h += '<section class="card panel"><h2>Add ' + e(kind[0].toUpperCase() + kind.slice(1)) + '</h2><form method="post" class="f-grid" data-action-form>'
                + '<input type="hidden" name="action" value="catalog_save"><input type="hidden" name="kind" value="' + e(kind) + '"><input type="hidden" name="return" value="setup.html">'
                + P.field('Name', P.input('name', { required: true, maxlength: kind === 'term' || kind === 'room' ? 40 : 100 }), '', 3);
            if (needsCollege) h += P.field('College', P.select_input('college_id', collegeOptions, { required: true }), '', 3);
            if (['college', 'program', 'course'].includes(kind)) h += P.field('Code', P.input('code', { required: true, maxlength: kind === 'college' ? 10 : 20 }));
            if (kind === 'college') {
                h += P.field('Short name', P.input('short_name', { required: true, maxlength: 40 })) + P.field('Building', P.input('building', { required: true, maxlength: 80 }));
            } else if (kind === 'course') {
                h += P.field('Units', P.input('units', { type: 'number', required: true, min: 1, max: 20 })) + P.field('Kind', P.select_input('course_kind', ['Lecture', 'Lab', 'PE', 'NSTP', 'Practicum']));
            } else if (kind === 'room') {
                h += P.field('Type', P.select_input('room_type', ['Lecture', 'Laboratory', 'Gym'])) + P.field('Capacity', P.input('capacity', { type: 'number', required: true, min: 1, max: 1000 }));
            } else if (kind === 'term') {
                h += P.field('Starts', P.input('starts_on', { type: 'date', required: true })) + P.field('Ends', P.input('ends_on', { type: 'date', required: true }))
                    + P.field('Grade deadline', P.input('grade_deadline', { type: 'date', required: true }), 'This term becomes the active term.');
            }
            h += '<div class="f f--3"><button type="submit" class="btn btn--primary btn--sm"' + (needsCollege && !collegeOptions.length ? ' disabled' : '') + '>Save ' + e(kind) + '</button></div></form></section>';
        }
        return h + '</div>' + page_end();
    }

    /* ================================================================== */
    /* Search                                                              */
    /* ================================================================== */

    function search() {
        const user = P.require_login();
        P.require_permission('academic.view', user);
        const term = q('q');
        const len = Array.from(term).length;
        let groups = [];
        if (len >= 2) {
            groups = [
                ['Students', 'users', 'enrollment.html', search_rows(P.students(), term, ['name', 'id', 'section']), r => [r.name, r.id + ' · ' + r.program_name, tag(r.status)]],
                ['Applicants', 'user-plus', 'incoming.html', search_rows(P.applicants(), term, ['name', 'id']), r => [r.name, r.id + ' · ' + r.program_name, tag(r.status)]],
                ['At-risk students', 'alert', 'at-risk.html', search_rows(P.at_risk(), term, ['name', 'id']), r => [r.name, r.id + ' · ' + r.detail, tag(r.type)]],
                ['Faculty', 'chart', 'evaluations.html', search_rows(P.faculty(), term, ['name']), r => [r.name, r.college + ' · ' + r.rank, '<span class="cell-rating"><strong>' + number_format(r.rating, 1) + '</strong>' + stars(r.rating) + '</span>']],
                ['Classes', 'calendar', 'grades.html', search_rows(P.classes(), term, ['code', 'title', 'section']), r => [r.code + ' · ' + r.title, r.section + ' · ' + r.faculty, tag(r.grade)]],
            ];
        }
        const found = sum(groups.map(g => g[3].length));

        let h = page_start('', 'Search', user);
        h += '<section class="page-head page-head--detail"><div><a class="crumb" href="index.html">' + icon('chev-l', 14) + ' Overview</a><h1>Search</h1><p class="muted">'
            + (term === '' ? 'Search students, applicants, faculty and classes across all colleges.' : (len < 2 ? 'Type at least 2 characters.' : number_format(found) + ' result' + (found === 1 ? '' : 's') + ' for “' + e(term) + '”'))
            + '</p></div></section>';
        if (term !== '' && len >= 2 && !found) h += '<section class="card panel"><p class="empty">Nothing matched. Try a last name, student number (e.g. 2023-10482), applicant ID or class code.</p></section>';
        h += '<div class="grid grid-2 search-results">';
        for (const [title, ic, pageFile, rows, show] of groups) {
            if (!rows.length) continue;
            h += '<section class="card panel panel--list"><div class="panel-head panel-pad"><div class="search-group-title"><span class="stat-icon tone-blue">' + icon(ic, 18) + '</span>'
                + '<div><h2>' + e(title) + '</h2><p class="muted small">' + number_format(rows.length) + ' match' + (rows.length === 1 ? '' : 'es') + '</p></div></div>'
                + '<a class="link-strong" href="' + e(pageFile + '?' + qs({ q: term })) + '#list">View all ' + icon('chev-r', 16) + '</a></div><ul class="list list--padded">';
            for (const r of rows.slice(0, 5)) {
                const [main, sub, side] = show(r);
                const key = r.id !== undefined && typeof r.id === 'string' ? r.id : (r.key || String(r.name).replace(/^(Dr|Prof|Engr)\.\s+/, ''));
                h += '<li class="list-row" data-href="' + e(pageFile + '?' + qs({ q: key })) + '#list">' + avatar(main)
                    + '<div class="list-main"><strong>' + e(main) + '</strong><span>' + e(sub) + '</span></div>' + side + icon('chev-r', 16, 'chev') + '</li>';
            }
            h += '</ul></section>';
        }
        return h + '</div>' + page_end();
    }

    /* ================================================================== */
    /* Old sign-in address: there is no sign-in in the static demo         */
    /* ================================================================== */

    function login() {
        location.replace('index.html');
        throw new P.Halt();
    }

    /* ================================================================== */
    /* Boot                                                                */
    /* ================================================================== */

    const PAGES = {
        overview, incoming, enrollment, schedule, grades, class: classPage, 'at-risk': atRisk,
        evaluations, predictions, setup, search, login,
    };
    const AFTER = { predictions: bindPredictions };

    const name = document.body.dataset.page;
    try {
        const render = PAGES[name];
        if (!render) throw new Error('Unknown page: ' + name);
        document.body.innerHTML = render();
        window.PORTAL_RENDERED = name;
        if (AFTER[name]) AFTER[name]();
        if (location.hash) {
            const target = document.getElementById(location.hash.slice(1));
            if (target) target.scrollIntoView();
        }
    } catch (err) {
        if (!(err instanceof P.Halt)) {
            console.error(err);
            document.body.innerHTML = '<main class="content"><section class="card panel"><h1>Something went wrong</h1><p class="muted">This page could not be built. Try <a href="#" id="resetLink">resetting the demo data</a>.</p></section></main>';
            document.getElementById('resetLink').addEventListener('click', ev => { ev.preventDefault(); P.db.reset(); location.reload(); });
        }
    }
})();
