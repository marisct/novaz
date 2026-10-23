import 'dart:convert';
import 'dart:async';

import 'package:flutter/foundation.dart' show kDebugMode;
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:url_launcher/url_launcher.dart';

const String defaultApiBaseUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'https://novaz.in/api',
);

const Color _ink = Color(0xFF111C3A);
const Color _muted = Color(0xFF666D7F);
const Color _line = Color(0xFFDCE2EF);
const Color _surface = Colors.white;
const Color _blue = Color(0xFF1D4ED8);
const Color _violet = Color(0xFF7C3AED);
const Color _teal = Color(0xFF0284C7);
const Color _amber = Color(0xFFB18A29);

void main() {
  runApp(const NovaXApp());
}

class NovaXApp extends StatelessWidget {
  const NovaXApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'NOVA Z',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(
          seedColor: _blue,
          primary: _blue,
          brightness: Brightness.light,
        ),
        scaffoldBackgroundColor: const Color(0xFFF4F6FA),
        textTheme: const TextTheme(
          headlineSmall: TextStyle(
            fontSize: 25,
            fontWeight: FontWeight.w700,
            color: _ink,
          ),
          titleLarge: TextStyle(
            fontSize: 21,
            fontWeight: FontWeight.w700,
            color: _ink,
          ),
          titleMedium: TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w600,
            color: _ink,
          ),
          bodyMedium: TextStyle(fontSize: 13, height: 1.5, color: _ink),
          bodySmall: TextStyle(fontSize: 11, height: 1.5, color: _muted),
        ),
        useMaterial3: true,
        appBarTheme: const AppBarTheme(
          centerTitle: false,
          elevation: 0,
          scrolledUnderElevation: 0,
          backgroundColor: Color(0xFFF4F6FA),
          foregroundColor: _ink,
          titleTextStyle: TextStyle(
            color: _ink,
            fontSize: 20,
            fontWeight: FontWeight.w800,
          ),
        ),
        cardTheme: CardThemeData(
          color: _surface,
          elevation: 0,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(8),
            side: const BorderSide(color: _line),
          ),
        ),
        inputDecorationTheme: InputDecorationTheme(
          filled: true,
          fillColor: _surface,
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(8),
            borderSide: const BorderSide(color: _line),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(8),
            borderSide: const BorderSide(color: _blue, width: 1.4),
          ),
        ),
        filledButtonTheme: FilledButtonThemeData(
          style: FilledButton.styleFrom(
            minimumSize: const Size(0, 48),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(8),
            ),
          ),
        ),
        outlinedButtonTheme: OutlinedButtonThemeData(
          style: OutlinedButton.styleFrom(
            minimumSize: const Size(0, 48),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(8),
            ),
          ),
        ),
      ),
      home: const AuthGate(),
    );
  }
}

class AuthGate extends StatefulWidget {
  const AuthGate({super.key});

  @override
  State<AuthGate> createState() => _AuthGateState();
}

class _AuthGateState extends State<AuthGate> {
  final _api = ApiClient(defaultApiBaseUrl);
  bool _loading = true;
  Session? _session;

  @override
  void initState() {
    super.initState();
    _restoreSession();
  }

  Future<void> _restoreSession() async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString('token');
    final userJson = prefs.getString('user');
    // A saved login is only valid for the server that issued it.
    if (token != null && prefs.getString('api_base') != _api.baseUrl) {
      await prefs.remove('token');
      await prefs.remove('user');
      setState(() => _loading = false);
      return;
    }
    if (token == null || userJson == null) {
      setState(() => _loading = false);
      return;
    }

    final session = Session(
      token: token,
      user: Map<String, dynamic>.from(jsonDecode(userJson) as Map),
    );

    setState(() {
      _session = session;
      _loading = false;
    });
  }

  Future<void> _saveSession(Session session) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('token', session.token);
    await prefs.setString('user', jsonEncode(session.user));
    await prefs.setString('api_base', _api.baseUrl);
    setState(() => _session = session);
  }

  Future<void> _logout() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('token');
    await prefs.remove('user');
    setState(() => _session = null);
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    final session = _session;
    if (session == null) {
      return LoginScreen(api: _api, onLogin: _saveSession);
    }

    return HomeScreen(api: _api, session: session, onLogout: _logout);
  }
}

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key, required this.api, required this.onLogin});

  final ApiClient api;
  final ValueChanged<Session> onLogin;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  // Demo login is pre-filled only in debug builds; the public app starts with empty fields.
  final _emailController = TextEditingController(text: kDebugMode ? 'admin@nonax.test' : '');
  final _passwordController = TextEditingController(text: kDebugMode ? 'password' : '');
  bool _submitting = false;
  bool _obscurePassword = true;
  String? _error;
  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate() || _submitting) return;
    FocusScope.of(context).unfocus();
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final session = await widget.api.login(
        email: _emailController.text.trim(),
        password: _passwordController.text,
      );
      if (mounted) widget.onLogin(session);
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    body: SafeArea(
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 520),
          child: ListView(
            padding: const EdgeInsets.fromLTRB(26, 30, 26, 30),
            children: [
              Row(
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(8),
                    child: Image.asset(
                      'assets/novax-logo.png',
                      width: 42,
                      height: 42,
                    ),
                  ),
                  const SizedBox(width: 12),
                  const Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'NOVA Z',
                        style: TextStyle(
                          fontSize: 23,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      Text(
                        'CENTRAL GOVT & BANK EXAM PREPARATION',
                        style: TextStyle(fontSize: 9, color: _muted),
                      ),
                    ],
                  ),
                ],
              ),
              const SizedBox(height: 26),
              ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: Image.asset(
                  'assets/study-desk.jpg',
                  height: 170,
                  width: double.infinity,
                  fit: BoxFit.cover,
                ),
              ),
              const SizedBox(height: 25),
              const Text(
                'Welcome back.',
                style: TextStyle(fontSize: 28, fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 6),
              const Text(
                'Your next chapter starts with a little practice.',
                style: TextStyle(color: _muted),
              ),
              const SizedBox(height: 25),
              Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    TextFormField(
                      controller: _emailController,
                      keyboardType: TextInputType.emailAddress,
                      textInputAction: TextInputAction.next,
                      autofillHints: const [AutofillHints.username],
                      decoration: const InputDecoration(
                        labelText: 'Email address',
                        prefixIcon: Icon(Icons.mail_outline, size: 19),
                      ),
                      validator: (value) =>
                          value == null || !value.contains('@')
                          ? 'Enter a valid email address'
                          : null,
                    ),
                    const SizedBox(height: 18),
                    TextFormField(
                      controller: _passwordController,
                      obscureText: _obscurePassword,
                      onFieldSubmitted: (_) => _submit(),
                      autofillHints: const [AutofillHints.password],
                      decoration: InputDecoration(
                        labelText: 'Password',
                        prefixIcon: const Icon(Icons.lock_outline, size: 19),
                        suffixIcon: IconButton(
                          tooltip: _obscurePassword
                              ? 'Show password'
                              : 'Hide password',
                          onPressed: () => setState(
                            () => _obscurePassword = !_obscurePassword,
                          ),
                          icon: Icon(
                            _obscurePassword
                                ? Icons.visibility_outlined
                                : Icons.visibility_off_outlined,
                            size: 19,
                          ),
                        ),
                      ),
                      validator: (value) => value == null || value.isEmpty
                          ? 'Enter your password'
                          : null,
                    ),
                    if (_error != null)
                      Padding(
                        padding: const EdgeInsets.only(top: 16),
                        child: _ErrorBox(message: _error!),
                      ),
                    const SizedBox(height: 24),
                    FilledButton.icon(
                      onPressed: _submitting ? null : _submit,
                      icon: _submitting
                          ? const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.arrow_forward, size: 19),
                      label: Text(_submitting ? 'Signing in' : 'Sign in'),
                    ),
                    const SizedBox(height: 22),
                    const Text(
                      'Learn with focus. Grow with confidence.',
                      textAlign: TextAlign.center,
                      style: TextStyle(fontSize: 11, color: _muted),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    ),
  );
}

class _MiniLogo extends StatelessWidget {
  const _MiniLogo();
  @override
  Widget build(BuildContext context) => ClipRRect(
    borderRadius: BorderRadius.circular(8),
    child: Image.asset(
      'assets/novax-logo.png',
      width: 42,
      height: 42,
      fit: BoxFit.contain,
    ),
  );
}

class _SubjectPerformanceRow extends StatelessWidget {
  const _SubjectPerformanceRow({required this.row});
  final Map<String, dynamic> row;

  @override
  Widget build(BuildContext context) {
    final accuracy = row['accuracy'] as num?;
    final barColor = accuracy == null
        ? _muted
        : accuracy >= 70
        ? const Color(0xFF15803D)
        : accuracy >= 40
        ? _amber
        : const Color(0xFFCF6E55);
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: _surface,
        border: Border.all(color: _line),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  asText(row['subject']),
                  style: const TextStyle(fontWeight: FontWeight.w700),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              Text(
                accuracy == null ? 'No answers' : '$accuracy%',
                style: TextStyle(fontWeight: FontWeight.w800, color: barColor),
              ),
            ],
          ),
          const SizedBox(height: 2),
          Text(
            asText(row['course']),
            style: const TextStyle(fontSize: 11, color: _muted),
          ),
          const SizedBox(height: 10),
          ClipRRect(
            borderRadius: BorderRadius.circular(999),
            child: LinearProgressIndicator(
              value: ((accuracy ?? 0) / 100).clamp(0.0, 1.0).toDouble(),
              minHeight: 7,
              backgroundColor: const Color(0xFFE3E8F3),
              color: barColor,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            '${row['correct'] ?? 0} correct  ·  ${row['wrong'] ?? 0} wrong  ·  ${row['skipped'] ?? 0} skipped  ·  ${row['questions'] ?? 0} questions',
            style: const TextStyle(fontSize: 11, color: _muted),
          ),
        ],
      ),
    );
  }
}

class _SectionHeader extends StatelessWidget {
  const _SectionHeader({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  color: _ink,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                subtitle,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(
                  context,
                ).textTheme.bodySmall?.copyWith(color: _muted),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _ModuleTile extends StatelessWidget {
  const _ModuleTile({
    required this.title,
    required this.icon,
    required this.color,
    required this.onTap,
  });

  final String title;
  final IconData icon;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: _surface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(8),
        side: const BorderSide(color: _line),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                height: 42,
                width: 42,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Icon(icon, color: color, size: 23),
              ),
              const Spacer(),
              Text(
                title,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  color: _ink,
                  fontWeight: FontWeight.w800,
                  height: 1.1,
                ),
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  Text(
                    'Open',
                    style: Theme.of(context).textTheme.labelMedium?.copyWith(
                      color: color,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(width: 4),
                  Icon(Icons.arrow_forward, size: 15, color: color),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({
    required this.label,
    required this.value,
    required this.icon,
    required this.color,
  });

  final String label;
  final String value;
  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: _surface,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: _line),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0F0F172A),
            blurRadius: 18,
            offset: Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                height: 34,
                width: 34,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Icon(icon, size: 19, color: color),
              ),
              const Spacer(),
            ],
          ),
          const Spacer(),
          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
              color: _ink,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 3),
          Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: _muted,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

Color _tileColor(int index) {
  const colors = [_blue, _teal, _violet, _amber, Color(0xFFDC2626)];
  return colors[index % colors.length];
}

IconData _metricIcon(String label) {
  final text = label.toLowerCase();
  if (text.contains('student')) return Icons.school_outlined;
  if (text.contains('teacher')) return Icons.groups_outlined;
  if (text.contains('course')) return Icons.menu_book_outlined;
  if (text.contains('subject')) return Icons.auto_stories_outlined;
  if (text.contains('attendance')) return Icons.fact_check_outlined;
  if (text.contains('exam') || text.contains('test')) {
    return Icons.assignment_outlined;
  }
  if (text.contains('score')) return Icons.insights_outlined;
  if (text.contains('question')) return Icons.quiz_outlined;
  if (text.contains('material') || text.contains('note')) {
    return Icons.folder_copy_outlined;
  }
  return Icons.dashboard_customize_outlined;
}

class HomeScreen extends StatefulWidget {
  const HomeScreen({
    super.key,
    required this.api,
    required this.session,
    required this.onLogout,
  });

  final ApiClient api;
  final Session session;
  final VoidCallback onLogout;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  late Future<HomeData> _homeFuture;
  int _selectedTab = 0;
  int _refreshToken = 0;

  @override
  void initState() {
    super.initState();
    _homeFuture = _loadHome();
  }

  Future<HomeData> _loadHome() async {
    final home = normalizeMap(
      await widget.api.get(widget.session.token, '/home'),
    );
    return HomeData(profile: home['profile'], dashboard: home['dashboard']);
  }

  void _refresh() {
    setState(() {
      _homeFuture = _loadHome();
      _refreshToken++;
    });
  }

  @override
  Widget build(BuildContext context) {
    final user = widget.session.user;
    final name = asText(user['name'], fallback: 'NOVA Z User');
    final role = asText(user['role'], fallback: 'user').replaceAll('_', ' ');

    final tabs = [
      _HomeTabSpec('Home', Icons.home_outlined),
      _HomeTabSpec('Courses', Icons.menu_book_outlined),
      _HomeTabSpec('Exams', Icons.assignment_outlined),
      _HomeTabSpec('Attend', Icons.fact_check_outlined),
      _HomeTabSpec('Profile', Icons.person_outline),
    ];

    return Scaffold(
      appBar: AppBar(
        title: const Row(
          children: [_MiniLogo(), SizedBox(width: 10), Text('NOVA Z')],
        ),
        actions: [
          IconButton(
            tooltip: 'Refresh',
            onPressed: _refresh,
            icon: const Icon(Icons.refresh),
          ),
          IconButton(
            tooltip: 'Logout',
            onPressed: widget.onLogout,
            icon: const Icon(Icons.logout),
          ),
        ],
      ),
      bottomNavigationBar: _NovaBottomBar(
        currentIndex: _selectedTab,
        items: tabs,
        onChanged: (index) => setState(() => _selectedTab = index),
      ),
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [Color(0xFFF4F6FA), Color(0xFFF4F6FA)],
          ),
        ),
        child: RefreshIndicator(
          onRefresh: () async => _refresh(),
          child: FutureBuilder<HomeData>(
            future: _homeFuture,
            builder: (context, snapshot) {
              if (snapshot.connectionState == ConnectionState.waiting) {
                return const Center(child: CircularProgressIndicator());
              }
              if (snapshot.hasError) {
                return ListView(
                  padding: const EdgeInsets.all(20),
                  children: [
                    _UserHero(name: name, role: role),
                    const SizedBox(height: 16),
                    _ErrorBox(message: snapshot.error.toString()),
                    const SizedBox(height: 12),
                    FilledButton.icon(
                      onPressed: _refresh,
                      icon: const Icon(Icons.refresh),
                      label: const Text('Try again'),
                    ),
                  ],
                );
              }

              return _HomeTabBody(
                selectedTab: _selectedTab,
                api: widget.api,
                token: widget.session.token,
                sessionUser: user,
                data: snapshot.data!,
                refreshToken: _refreshToken,
              );
            },
          ),
        ),
      ),
    );
  }
}

class _HomeTabBody extends StatelessWidget {
  const _HomeTabBody({
    required this.selectedTab,
    required this.api,
    required this.token,
    required this.sessionUser,
    required this.data,
    required this.refreshToken,
  });

  final int selectedTab;
  final ApiClient api;
  final String token;
  final Map<String, dynamic> sessionUser;
  final HomeData data;
  final int refreshToken;

  @override
  Widget build(BuildContext context) {
    final name = asText(sessionUser['name'], fallback: 'NOVA Z User');
    final role = asText(
      sessionUser['role'],
      fallback: 'user',
    ).replaceAll('_', ' ');
    final rawRole = asText(sessionUser['role']);

    return switch (selectedTab) {
      0 => ListView(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 110),
        children: [
          _UserHero(name: name, role: role),
          const SizedBox(height: 20),
          _DashboardSection(data.dashboard),
          if (rawRole == 'student') ...[
            const SizedBox(height: 24),
            _StudentProgress(
              dashboard: normalizeMap(data.dashboard),
              api: api,
              token: token,
            ),
          ],
          const SizedBox(height: 20),
          _QuickLinks(api: api, token: token, role: rawRole),
        ],
      ),
      1 => _EndpointTabList(
        key: ValueKey('courses-$refreshToken'),
        api: api,
        token: token,
        endpoint: '/courses',
        title: 'Courses',
        subtitle: 'Explore active programmes and batches',
        icon: Icons.menu_book_outlined,
      ),
      2 => _EndpointTabList(
        key: ValueKey('exams-$refreshToken'),
        api: api,
        token: token,
        endpoint: '/exams',
        title: 'Exams',
        subtitle: 'Daily, weekly, monthly and mock tests',
        icon: Icons.assignment_outlined,
        showExamCards: true,
      ),
      3 => _EndpointTabList(
        key: ValueKey('attendance-$refreshToken'),
        api: api,
        token: token,
        endpoint: '/attendance',
        title: 'Attendance',
        subtitle: 'Track attendance records and status',
        icon: Icons.fact_check_outlined,
      ),
      _ => ListView(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 110),
        children: [
          _UserHero(name: name, role: role),
          const SizedBox(height: 20),
          _ProfileSection(data.profile),
          const SizedBox(height: 20),
          const _SectionHeader(
            title: 'Account',
            subtitle: 'Role and API session details',
          ),
          const SizedBox(height: 10),
          _DataCard(data: normalizeMap(sessionUser)),
        ],
      ),
    };
  }
}

class _EndpointTabList extends StatefulWidget {
  const _EndpointTabList({
    super.key,
    required this.api,
    required this.token,
    required this.endpoint,
    required this.title,
    required this.subtitle,
    required this.icon,
    this.showExamCards = false,
  });

  final ApiClient api;
  final String token;
  final String endpoint;
  final String title;
  final String subtitle;
  final IconData icon;
  final bool showExamCards;

  @override
  State<_EndpointTabList> createState() => _EndpointTabListState();
}

class _EndpointTabListState extends State<_EndpointTabList> {
  String _query = '';
  late Future<dynamic> _future;

  @override
  void initState() {
    super.initState();
    _future = widget.api.get(widget.token, widget.endpoint);
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<dynamic>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return ListView(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 110),
            children: [_ErrorBox(message: snapshot.error.toString())],
          );
        }

        final allItems = extractItems(snapshot.data);
        if (allItems.isEmpty) {
          return _EmptyState(
            icon: widget.icon,
            title: 'No ${widget.title.toLowerCase()} found',
            subtitle: 'Records from your institute will appear here.',
          );
        }

        final items = allItems
            .where(
              (item) =>
                  _query.isEmpty ||
                  asText(item['name']).toLowerCase().contains(_query),
            )
            .toList();
        return ListView.separated(
          padding: const EdgeInsets.fromLTRB(20, 20, 20, 110),
          itemCount: items.length + 1,
          separatorBuilder: (_, index) => index == 0
              ? const SizedBox(height: 14)
              : const SizedBox(height: 10),
          itemBuilder: (context, index) {
            if (index == 0) {
              return Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _TabHero(
                    title: widget.title,
                    subtitle: widget.subtitle,
                    icon: widget.icon,
                  ),
                  if (widget.endpoint == '/courses') ...[
                    const SizedBox(height: 18),
                    TextField(
                      decoration: const InputDecoration(
                        hintText: 'Search courses',
                        prefixIcon: Icon(Icons.search, size: 20),
                      ),
                      onChanged: (value) =>
                          setState(() => _query = value.trim().toLowerCase()),
                    ),
                    if (items.isEmpty)
                      const Padding(
                        padding: EdgeInsets.only(top: 20),
                        child: Text('No courses match your search.'),
                      ),
                  ],
                ],
              );
            }
            final item = items[index - 1];
            if (widget.endpoint == '/courses') {
              return _CourseCard(
                course: item,
                api: widget.api,
                token: widget.token,
              );
            }
            if (widget.showExamCards) {
              return _ExamCard(
                api: widget.api,
                token: widget.token,
                exam: item,
              );
            }
            return _DataCard(data: item);
          },
        );
      },
    );
  }
}

class _CourseCard extends StatelessWidget {
  const _CourseCard({
    required this.course,
    required this.api,
    required this.token,
  });
  final Map<String, dynamic> course;
  final ApiClient api;
  final String token;
  @override
  Widget build(BuildContext context) {
    final name = asText(course['name']);
    final railway = name.toLowerCase().contains('rrb');
    final subjects = extractItems(course['subjects']);
    return Card(
      margin: EdgeInsets.zero,
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => Scaffold(
              appBar: AppBar(title: Text(name)),
              body: ListView(
                padding: const EdgeInsets.all(22),
                children: [
                  Text(name, style: Theme.of(context).textTheme.headlineSmall),
                  const SizedBox(height: 12),
                  if (asText(course['description']).isNotEmpty)
                    Text(asText(course['description'])),
                  const SizedBox(height: 26),
                  const Text(
                    'Course subjects',
                    style: TextStyle(fontSize: 19, fontWeight: FontWeight.w700),
                  ),
                  if (subjects.isEmpty)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 20),
                      child: Text('Subjects will appear here when assigned.'),
                    ),
                  for (final subject in subjects)
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: const Icon(
                        Icons.menu_book_outlined,
                        color: _blue,
                      ),
                      title: Text(asText(subject['name'])),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: () => Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => ApiListScreen(
                            api: api,
                            token: token,
                            title: asText(subject['name']),
                            endpoint:
                                '/notes?course_id=${course['id']}&subject_id=${subject['id']}',
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              height: 105,
              color: railway
                  ? const Color(0xFFF7EDCD)
                  : const Color(0xFFDEE5F6),
              padding: const EdgeInsets.all(22),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Icon(
                    railway ? Icons.train_outlined : Icons.school_outlined,
                    size: 38,
                    color: railway ? _amber : _blue,
                  ),
                  Text(
                    railway
                        ? 'RRB'
                        : name.toLowerCase().contains('ssc')
                        ? 'SSC'
                        : 'LEARN',
                    style: TextStyle(
                      fontSize: 30,
                      fontWeight: FontWeight.w700,
                      color: railway ? _amber : _blue,
                    ),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    name,
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Text(
                    '${subjects.length} subjects${asText(course['duration']).isEmpty ? '' : '  |  ${course['duration']}'}',
                    style: const TextStyle(color: _muted, fontSize: 12),
                  ),
                  const SizedBox(height: 18),
                  const Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'Explore course',
                        style: TextStyle(
                          color: _blue,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      Icon(Icons.arrow_forward, color: _blue, size: 19),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _TabHero extends StatelessWidget {
  const _TabHero({
    required this.title,
    required this.subtitle,
    required this.icon,
  });
  final String title;
  final String subtitle;
  final IconData icon;
  @override
  Widget build(BuildContext context) => Row(
    children: [
      Icon(icon, color: _blue, size: 28),
      const SizedBox(width: 14),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: Theme.of(context).textTheme.headlineSmall),
            const SizedBox(height: 5),
            Text(subtitle, style: const TextStyle(color: _muted, fontSize: 12)),
          ],
        ),
      ),
    ],
  );
}

class _NovaBottomBar extends StatelessWidget {
  const _NovaBottomBar({
    required this.currentIndex,
    required this.items,
    required this.onChanged,
  });

  final int currentIndex;
  final List<_HomeTabSpec> items;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 6, 14, 12),
        child: Container(
          height: 80,
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
          decoration: BoxDecoration(
            color: _surface,
            borderRadius: BorderRadius.circular(8),
            border: Border.all(color: _line),
            boxShadow: const [
              BoxShadow(
                color: Color(0x1A0F172A),
                blurRadius: 24,
                offset: Offset(0, 12),
              ),
            ],
          ),
          child: Row(
            children: [
              for (var index = 0; index < items.length; index++)
                Expanded(
                  child: _BottomNavItem(
                    item: items[index],
                    selected: currentIndex == index,
                    onTap: () => onChanged(index),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _BottomNavItem extends StatelessWidget {
  const _BottomNavItem({
    required this.item,
    required this.selected,
    required this.onTap,
  });

  final _HomeTabSpec item;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(8),
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        curve: Curves.easeOut,
        margin: const EdgeInsets.symmetric(horizontal: 2),
        padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 7),
        decoration: BoxDecoration(
          color: selected ? const Color(0xFFE1E8FA) : Colors.transparent,
          borderRadius: BorderRadius.circular(8),
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(item.icon, size: 21, color: selected ? _blue : _muted),
            const SizedBox(height: 4),
            Text(
              item.label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                color: selected ? _blue : _muted,
                fontSize: 10,
                fontWeight: selected ? FontWeight.w900 : FontWeight.w700,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _HomeTabSpec {
  const _HomeTabSpec(this.label, this.icon);

  final String label;
  final IconData icon;
}

class _QuickLinks extends StatelessWidget {
  const _QuickLinks({
    required this.api,
    required this.token,
    required this.role,
  });

  final ApiClient api;
  final String token;
  final String role;

  @override
  Widget build(BuildContext context) {
    final links = [
      _ApiLink('Courses', Icons.menu_book_outlined, '/courses'),
      _ApiLink('Exams', Icons.assignment_outlined, '/exams'),
      _ApiLink('Attendance', Icons.fact_check_outlined, '/attendance'),
      _ApiLink('Notes', Icons.picture_as_pdf_outlined, '/notes'),
      _ApiLink(
        'Previous Papers',
        Icons.history_edu_outlined,
        '/previous-papers',
      ),
    ];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const _SectionHeader(
          title: 'Quick access',
          subtitle: 'Daily student and institute actions',
        ),
        const SizedBox(height: 10),
        LayoutBuilder(
          builder: (context, constraints) {
            final columns = constraints.maxWidth > 620 ? 4 : 2;
            return GridView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: links.length,
              gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: columns,
                childAspectRatio: columns == 4 ? 1.2 : 1.18,
                crossAxisSpacing: 12,
                mainAxisSpacing: 12,
              ),
              itemBuilder: (context, index) {
                final link = links[index];
                return _ModuleTile(
                  title: link.title,
                  icon: link.icon,
                  color: _tileColor(index),
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => ApiListScreen(
                        api: api,
                        token: token,
                        title: link.title,
                        endpoint: link.endpoint,
                      ),
                    ),
                  ),
                );
              },
            );
          },
        ),
        if (role == 'super_admin' || role == 'teacher') ...[
          const SizedBox(height: 20),
          const _SectionHeader(
            title: 'Management',
            subtitle: 'Create, edit and review institute records',
          ),
          const SizedBox(height: 10),
          LayoutBuilder(
            builder: (context, constraints) {
              final configs = resourceConfigs(role);
              final columns = constraints.maxWidth > 620 ? 4 : 2;
              return GridView.builder(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                itemCount: configs.length,
                gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: columns,
                  childAspectRatio: columns == 4 ? 1.2 : 1.12,
                  crossAxisSpacing: 12,
                  mainAxisSpacing: 12,
                ),
                itemBuilder: (context, index) {
                  final config = configs[index];
                  return _ModuleTile(
                    title: config.title,
                    icon: config.icon,
                    color: _tileColor(index + 2),
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => ResourceListScreen(
                          api: api,
                          token: token,
                          config: config,
                        ),
                      ),
                    ),
                  );
                },
              );
            },
          ),
        ],
      ],
    );
  }
}

class ApiListScreen extends StatefulWidget {
  const ApiListScreen({
    super.key,
    required this.api,
    required this.token,
    required this.title,
    required this.endpoint,
  });

  final ApiClient api;
  final String token;
  final String title;
  final String endpoint;

  @override
  State<ApiListScreen> createState() => _ApiListScreenState();
}

class _ApiListScreenState extends State<ApiListScreen> {
  late Future<dynamic> _future;

  @override
  void initState() {
    super.initState();
    _future = widget.api.get(widget.token, widget.endpoint);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.title)),
      body: FutureBuilder<dynamic>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Padding(
              padding: const EdgeInsets.all(20),
              child: _ErrorBox(message: snapshot.error.toString()),
            );
          }
          final items = extractItems(snapshot.data);
          if (items.isEmpty) {
            return _EmptyState(
              icon: Icons.inbox_outlined,
              title: 'No data found',
              subtitle: 'New records will appear here after they are added.',
            );
          }
          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: items.length,
            separatorBuilder: (_, _) => const SizedBox(height: 10),
            itemBuilder: (context, index) {
              final item = items[index];
              if (widget.endpoint.contains('test') ||
                  widget.endpoint.contains('quiz')) {
                return _ExamCard(
                  api: widget.api,
                  token: widget.token,
                  exam: item,
                );
              }
              return _DataCard(data: item);
            },
          );
        },
      ),
    );
  }
}

String _shortDate(dynamic iso) {
  final date = DateTime.tryParse(asText(iso))?.toLocal();
  if (date == null) return '';
  const months = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
  ];
  final hh = date.hour.toString().padLeft(2, '0');
  final mm = date.minute.toString().padLeft(2, '0');
  return '${date.day} ${months[date.month - 1]}, $hh:$mm';
}

String _examWindow(Map<String, dynamic> exam) {
  final start = _shortDate(exam['starts_at']);
  final end = exam['ends_at'] == null ? '' : _shortDate(exam['ends_at']);
  return end.isEmpty ? start : '$start - $end';
}

class _ExamCard extends StatelessWidget {
  const _ExamCard({required this.api, required this.token, required this.exam});

  final ApiClient api;
  final String token;
  final Map<String, dynamic> exam;

  Future<void> _start(BuildContext context) async {
    try {
      final payload = await api.post(token, '/exams/${exam['id']}/start', {});
      if (!context.mounted) return;
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) =>
              ExamTakeScreen(api: api, token: token, payload: payload),
        ),
      );
    } catch (error) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.toString())));
    }
  }

  @override
  Widget build(BuildContext context) {
    final attempts = (exam['attempts'] as List? ?? const [])
        .map((a) => normalizeMap(a))
        .toList();
    final done = attempts.any((a) => a['status'] == 'submitted');
    final course = exam['course'] is Map ? exam['course']['name'] : null;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _surface,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: _line),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0D0F172A),
            blurRadius: 16,
            offset: Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                height: 44,
                width: 44,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: _violet.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(Icons.assignment_outlined, color: _violet),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  asText(exam['title'], fallback: 'Exam'),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                    color: _ink,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              if (course != null)
                _MetaPill(icon: Icons.menu_book_outlined, label: course),
              _MetaPill(
                icon: Icons.quiz_outlined,
                label: '${formatValue(exam['total_questions'])} questions',
              ),
              _MetaPill(
                icon: Icons.timer_outlined,
                label: '${formatValue(exam['duration_minutes'])} min',
              ),
              if (exam['starts_at'] != null)
                _MetaPill(icon: Icons.event_outlined, label: _examWindow(exam)),
            ],
          ),
          const SizedBox(height: 12),
          Align(
            alignment: Alignment.centerRight,
            child: FilledButton.icon(
              onPressed:
                  exam['state'] == 'upcoming' || exam['state'] == 'closed'
                  ? null
                  : () => _start(context),
              icon: Icon(
                exam['state'] == 'closed'
                    ? Icons.lock_outline
                    : exam['state'] == 'upcoming'
                    ? Icons.schedule
                    : done
                    ? Icons.fact_check_outlined
                    : Icons.play_arrow,
              ),
              label: Text(
                exam['state'] == 'closed'
                    ? 'Closed'
                    : exam['state'] == 'upcoming'
                    ? 'Opens ${_shortDate(exam['starts_at'])}'
                    : done
                    ? 'View Result'
                    : 'Start Test',
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class ExamTakeScreen extends StatefulWidget {
  const ExamTakeScreen({
    super.key,
    required this.api,
    required this.token,
    required this.payload,
  });

  final ApiClient api;
  final String token;
  final dynamic payload;

  @override
  State<ExamTakeScreen> createState() => _ExamTakeScreenState();
}

class _ExamTakeScreenState extends State<ExamTakeScreen> {
  late Map<String, dynamic> _payload;
  final _answers = <String, String>{};
  bool _saving = false;
  Timer? _ticker;
  Timer? _autosave;
  late DateTime _deadline;
  DateTime? _retryAt;
  final _typing = TextEditingController();
  int get _remaining =>
      (_deadline.difference(DateTime.now()).inMilliseconds / 1000).ceil().clamp(
        0,
        100000,
      );

  void _setDeadline() {
    final seconds = (_payload['remaining_seconds'] as num? ?? 0).toInt();
    _deadline = DateTime.now().add(Duration(seconds: seconds));
  }

  @override
  void dispose() {
    _ticker?.cancel();
    _autosave?.cancel();
    _typing.dispose();
    super.dispose();
  }

  void _saveSoon() {
    _autosave?.cancel();
    _autosave = Timer(const Duration(milliseconds: 350), () {
      if (!mounted) return;
      if (_saving) {
        _saveSoon();
        return;
      }
      _send('save');
    });
  }

  @override
  void initState() {
    super.initState();
    _payload = normalizeMap(widget.payload);
    _typing.text = asText(
      normalizeMap(_payload['attempt'])['dest_text'],
      fallback: '',
    );
    _setDeadline();
    _ticker = Timer.periodic(const Duration(seconds: 1), (_) {
      if (!mounted ||
          _payload['status'] == 'result' ||
          _payload['session_break'] == true) {
        return;
      }
      setState(() {});
      if (_remaining == 0 &&
          !_saving &&
          (_retryAt == null || DateTime.now().isAfter(_retryAt!))) {
        _send('continue');
      }
    });
    final existing = normalizeMap(_payload['answers']);
    for (final entry in existing.entries) {
      final row = normalizeMap(entry.value);
      if (row['answer'] != null) _answers[entry.key] = row['answer'].toString();
    }
  }

  Future<void> _confirmSubmit() async {
    final pattern = normalizeMap(normalizeMap(_payload['exam'])['pattern']);
    final sectioned = pattern['timer_mode'] == 'section';
    final questions = (_payload['questions'] as List? ?? const [])
        .map((q) => normalizeMap(q))
        .toList();
    final answered = questions
        .where((q) => _answers.containsKey(q['id'].toString()))
        .length;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(sectioned ? 'Lock this section?' : 'Submit exam?'),
        content: Text(
          sectioned
              ? 'Answered $answered of ${questions.length} in this section. You cannot come back to it.'
              : 'Answered $answered of ${questions.length} questions. Unanswered questions count as skipped.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(sectioned ? 'Lock & continue' : 'Submit'),
          ),
        ],
      ),
    );
    if (confirmed == true) await _send('continue');
  }

  Future<void> _send(String action) async {
    if (_saving) return;
    setState(() => _saving = true);
    try {
      final attempt = normalizeMap(_payload['attempt']);
      final next = await widget.api
          .post(widget.token, '/attempts/${attempt['id']}', {
            'answers': Map.of(_answers),
            'action': action,
            'section_key': attempt['current_section_key'],
            'dest_text': _typing.text,
          });
      final nextMap = normalizeMap(next);
      _retryAt = null;
      if (!mounted) return;
      if (nextMap['status'] == 'result') {
        _ticker?.cancel();
        _autosave?.cancel();
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(builder: (_) => ExamResultScreen(payload: nextMap)),
        );
      } else {
        setState(() {
          if (normalizeMap(nextMap['attempt'])['current_section_key'] !=
              attempt['current_section_key']) {
            _answers.clear();
            _typing.text = asText(
              normalizeMap(nextMap['attempt'])['dest_text'],
              fallback: '',
            );
          }
          _payload = nextMap;
          _setDeadline();
        });
      }
    } catch (error) {
      if (mounted) {
        _retryAt = DateTime.now().add(const Duration(seconds: 5));
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.toString())));
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_payload['status'] == 'result') {
      return ExamResultScreen(payload: _payload);
    }

    final exam = normalizeMap(_payload['exam']);
    final attempt = normalizeMap(_payload['attempt']);
    final questions = extractItems(_payload['questions']);
    final remaining = _remaining;
    final isBreak = _payload['session_break'] == true;
    final typing = _payload['typing_session'] == true;
    final pattern = normalizeMap(exam['pattern']);
    final timedSection =
        normalizeMap(pattern['settings'])['official_mock'] == true &&
        pattern['timer_mode'] == 'section';

    return Scaffold(
      appBar: AppBar(title: Text(asText(exam['title'], fallback: 'Take Test'))),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: _saving || isBreak || remaining == 0
                      ? null
                      : () => _send('save'),
                  icon: const Icon(Icons.save_outlined),
                  label: const Text('Save'),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: FilledButton.icon(
                  onPressed:
                      _saving || (timedSection && !isBreak && remaining > 0)
                      ? null
                      : () => isBreak ? _send('start_dest') : _confirmSubmit(),
                  icon: const Icon(Icons.navigate_next),
                  label: Text(
                    isBreak
                        ? 'Start typing session'
                        : timedSection && remaining > 0
                        ? 'Timed section'
                        : 'Submit / Next',
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: const Color(0xFF0F172A),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Row(
              children: [
                const Icon(Icons.timer_outlined, color: Colors.white),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    'Remaining: ${remaining ~/ 60}:${(remaining % 60).toString().padLeft(2, '0')}'
                    '  |  ${pattern['timer_mode'] == 'section' ? 'Section: ${formatValue(attempt['current_section_key'])}' : 'All sections'}',
                    style: const TextStyle(color: Colors.white),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          if (isBreak)
            const Text(
              'Session I complete. Start Session II when ready. Typing duration: 15 minutes.',
            ),
          if (typing && !isBreak) ...[
            SelectableText(asText(exam['dest_passage'])),
            const SizedBox(height: 16),
            TextField(
              controller: _typing,
              enabled: remaining > 0,
              maxLines: 10,
              maxLength: 10000,
              autocorrect: false,
              enableSuggestions: false,
              enableInteractiveSelection: false,
              decoration: const InputDecoration(labelText: 'Type the passage'),
              onChanged: (_) => _saveSoon(),
            ),
          ],
          ...questions.map(
            (question) => IgnorePointer(
              ignoring: remaining == 0 || isBreak,
              child: _QuestionCard(
                question: question,
                selected: _answers[question['id'].toString()],
                onChanged: (value) {
                  setState(() => _answers[question['id'].toString()] = value);
                  _saveSoon();
                },
              ),
            ),
          ),
          const SizedBox(height: 80),
        ],
      ),
    );
  }
}

class _QuestionCard extends StatelessWidget {
  const _QuestionCard({
    required this.question,
    required this.selected,
    required this.onChanged,
  });

  final Map<String, dynamic> question;
  final String? selected;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    final options = normalizeMap(question['options']);
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: _surface,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: _line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            asText(question['question']),
            style: Theme.of(
              context,
            ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 10),
          ...options.entries
              .where((entry) => asText(entry.value).isNotEmpty)
              .map(
                (entry) => InkWell(
                  borderRadius: BorderRadius.circular(8),
                  onTap: () => onChanged(entry.key),
                  child: Container(
                    margin: const EdgeInsets.only(bottom: 8),
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: selected == entry.key
                          ? _blue.withValues(alpha: 0.08)
                          : const Color(0xFFF8FAFC),
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(
                        color: selected == entry.key ? _blue : _line,
                      ),
                    ),
                    child: Row(
                      children: [
                        Icon(
                          selected == entry.key
                              ? Icons.radio_button_checked
                              : Icons.radio_button_unchecked,
                          color: selected == entry.key ? _blue : _muted,
                        ),
                        const SizedBox(width: 10),
                        Expanded(child: Text('${entry.key}. ${entry.value}')),
                      ],
                    ),
                  ),
                ),
              ),
        ],
      ),
    );
  }
}

class ExamResultScreen extends StatelessWidget {
  const ExamResultScreen({super.key, required this.payload});

  final Map<String, dynamic> payload;

  @override
  Widget build(BuildContext context) {
    final attempt = normalizeMap(payload['attempt']);
    final exam = normalizeMap(payload['exam']);
    final sectionScores = normalizeMap(payload['section_scores']);
    return Scaffold(
      appBar: AppBar(title: const Text('Result')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: const Color(0xFF0F172A),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  asText(exam['title'], fallback: 'Exam Result'),
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  'Score: ${formatValue(attempt['score'])}',
                  style: Theme.of(
                    context,
                  ).textTheme.headlineMedium?.copyWith(color: Colors.white),
                ),
                Text(
                  'Correct ${formatValue(attempt['correct_count'])} | Wrong ${formatValue(attempt['wrong_count'])} | Skipped ${formatValue(attempt['skipped_count'])}',
                  style: const TextStyle(color: Color(0xFFBFDBFE)),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          Text(
            'Section Breakdown',
            style: Theme.of(context).textTheme.titleLarge,
          ),
          const SizedBox(height: 10),
          if (sectionScores.isEmpty)
            _DataCard(data: attempt)
          else
            ...sectionScores.values.map(
              (row) => Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: _DataCard(data: normalizeMap(row)),
              ),
            ),
        ],
      ),
    );
  }
}

class ResourceListScreen extends StatefulWidget {
  const ResourceListScreen({
    super.key,
    required this.api,
    required this.token,
    required this.config,
  });

  final ApiClient api;
  final String token;
  final ResourceConfig config;

  @override
  State<ResourceListScreen> createState() => _ResourceListScreenState();
}

class _ResourceListScreenState extends State<ResourceListScreen> {
  late Future<dynamic> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<dynamic> _load() =>
      widget.api.get(widget.token, widget.config.endpoint);

  void _refresh() {
    setState(() => _future = _load());
  }

  Future<void> _openForm([Map<String, dynamic>? item]) async {
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => ResourceFormScreen(
          api: widget.api,
          token: widget.token,
          config: widget.config,
          item: item,
        ),
      ),
    );
    if (changed == true) _refresh();
  }

  Future<void> _delete(Map<String, dynamic> item) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Delete ${widget.config.singular}?'),
        content: Text(firstText(item, widget.config.titleKeys) ?? 'This item'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Delete'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;

    try {
      await widget.api.delete(
        widget.token,
        '${widget.config.endpoint}/${item['id']}',
      );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('${widget.config.singular} deleted')),
        );
      }
      _refresh();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.toString())));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.config.title),
        actions: [
          IconButton(
            tooltip: 'Refresh',
            onPressed: _refresh,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      floatingActionButton: widget.config.canCreate
          ? FloatingActionButton.extended(
              onPressed: () => _openForm(),
              icon: const Icon(Icons.add),
              label: Text('Add ${widget.config.singular}'),
            )
          : null,
      body: FutureBuilder<dynamic>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Padding(
              padding: const EdgeInsets.all(20),
              child: _ErrorBox(message: snapshot.error.toString()),
            );
          }
          final items = extractItems(snapshot.data);
          if (items.isEmpty) {
            return _EmptyState(
              icon: widget.config.icon,
              title: 'No ${widget.config.title.toLowerCase()} yet',
              subtitle:
                  'Tap Add ${widget.config.singular} to create the first record.',
            );
          }
          return ListView.separated(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
            itemCount: items.length,
            separatorBuilder: (_, _) => const SizedBox(height: 10),
            itemBuilder: (context, index) {
              final item = items[index];
              return _ResourceRow(
                icon: widget.config.icon,
                color: _tileColor(index),
                title:
                    firstText(item, widget.config.titleKeys) ??
                    '${widget.config.singular} #${item['id']}',
                subtitle: widget.config.subtitle(item),
                onEdit: widget.config.canCreate ? () => _openForm(item) : null,
                onDelete: widget.config.canDelete ? () => _delete(item) : null,
              );
            },
          );
        },
      ),
    );
  }
}

class ResourceFormScreen extends StatefulWidget {
  const ResourceFormScreen({
    super.key,
    required this.api,
    required this.token,
    required this.config,
    this.item,
  });

  final ApiClient api;
  final String token;
  final ResourceConfig config;
  final Map<String, dynamic>? item;

  @override
  State<ResourceFormScreen> createState() => _ResourceFormScreenState();
}

class _ResourceFormScreenState extends State<ResourceFormScreen> {
  Future<dynamic>? _patternsFuture;
  final _formKey = GlobalKey<FormState>();
  final _controllers = <String, TextEditingController>{};
  bool _saving = false;

  bool get _editing => widget.item != null;

  @override
  void initState() {
    super.initState();
    if (widget.config.fields.any((field) => field.name == 'exam_pattern_id')) {
      _patternsFuture = widget.api.get(widget.token, '/exam-patterns');
    }
    for (final field in widget.config.fields) {
      _controllers[field.name] = TextEditingController(
        text: initialFieldValue(widget.item, field),
      );
    }
  }

  @override
  void dispose() {
    for (final controller in _controllers.values) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _saving = true);
    try {
      final payload = <String, dynamic>{};
      for (final field in widget.config.fields) {
        final raw = _controllers[field.name]!.text.trim();
        if (raw.isEmpty && !field.required) continue;
        payload[field.name] = field.toPayload(raw);
      }

      if (_editing) {
        await widget.api.put(
          widget.token,
          '${widget.config.endpoint}/${widget.item!['id']}',
          payload,
        );
      } else {
        await widget.api.post(widget.token, widget.config.endpoint, payload);
      }

      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.toString())));
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('${_editing ? 'Edit' : 'Add'} ${widget.config.singular}'),
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: _surface,
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: _line),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      Container(
                        height: 42,
                        width: 42,
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                          color: _blue.withValues(alpha: 0.12),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Icon(widget.config.icon, color: _blue),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          '${_editing ? 'Edit' : 'Add'} ${widget.config.singular}',
                          style: Theme.of(context).textTheme.titleLarge
                              ?.copyWith(
                                color: _ink,
                                fontWeight: FontWeight.w900,
                              ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 18),
                  ...widget.config.fields.map((field) {
                    if (field.name == 'exam_pattern_id') {
                      return FutureBuilder<dynamic>(
                        future: _patternsFuture,
                        builder: (context, snapshot) {
                          if (snapshot.hasError) {
                            return TextButton.icon(
                              onPressed: () => setState(
                                () => _patternsFuture = widget.api.get(
                                  widget.token,
                                  '/exam-patterns',
                                ),
                              ),
                              icon: const Icon(Icons.refresh),
                              label: const Text('Retry loading exam patterns'),
                            );
                          }
                          if (!snapshot.hasData) {
                            return const LinearProgressIndicator();
                          }
                          final patterns = extractItems(snapshot.data);
                          final selected = _controllers[field.name]!.text;
                          final matched = patterns.where(
                            (p) => p['id'].toString() == selected,
                          );
                          return Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              DropdownButtonFormField<String>(
                                isExpanded: true,
                                initialValue: matched.isEmpty ? null : selected,
                                decoration: const InputDecoration(
                                  labelText: 'Exam pattern',
                                ),
                                items: patterns
                                    .map(
                                      (p) => DropdownMenuItem(
                                        value: p['id'].toString(),
                                        child: Text(
                                          asText(p['name']),
                                          overflow: TextOverflow.ellipsis,
                                        ),
                                      ),
                                    )
                                    .toList(),
                                onChanged: _editing
                                    ? null
                                    : (value) => setState(() {
                                        _controllers[field.name]!.text =
                                            value ?? '';
                                      }),
                                validator: (value) =>
                                    field.required &&
                                        (value == null || value.isEmpty)
                                    ? 'Choose a pattern'
                                    : null,
                              ),
                              if (matched.isNotEmpty)
                                ...extractItems(matched.first['sections']).map(
                                  (section) => Padding(
                                    padding: const EdgeInsets.only(top: 6),
                                    child: Text(
                                      '${section['name']}: ${section['question_count']} questions${section['duration_minutes'] == null ? "" : ", ${section['duration_minutes']} min"}',
                                    ),
                                  ),
                                ),
                              const SizedBox(height: 12),
                            ],
                          );
                        },
                      );
                    }
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: TextFormField(
                        controller: _controllers[field.name],
                        keyboardType: field.keyboardType,
                        maxLines: field.maxLines,
                        decoration: InputDecoration(
                          labelText: field.label,
                          helperText: field.helper,
                        ),
                        validator: (value) {
                          if (field.required &&
                              (value == null || value.trim().isEmpty)) {
                            return '${field.label} is required';
                          }
                          return null;
                        },
                      ),
                    );
                  }),
                  const SizedBox(height: 8),
                  FilledButton.icon(
                    onPressed: _saving ? null : _save,
                    icon: _saving
                        ? const SizedBox(
                            height: 18,
                            width: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.save_outlined),
                    label: Text(_saving ? 'Saving' : 'Save'),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ResourceRow extends StatelessWidget {
  const _ResourceRow({
    required this.icon,
    required this.color,
    required this.title,
    required this.subtitle,
    required this.onEdit,
    required this.onDelete,
  });

  final IconData icon;
  final Color color;
  final String title;
  final String subtitle;
  final VoidCallback? onEdit;
  final VoidCallback? onDelete;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: _surface,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: _line),
      ),
      child: Row(
        children: [
          Container(
            height: 44,
            width: 44,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Icon(icon, color: color),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    color: _ink,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  subtitle,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(
                    context,
                  ).textTheme.bodySmall?.copyWith(color: _muted),
                ),
              ],
            ),
          ),
          IconButton(
            tooltip: 'Edit',
            onPressed: onEdit,
            icon: const Icon(Icons.edit_outlined),
          ),
          IconButton(
            tooltip: 'Delete',
            onPressed: onDelete,
            color: const Color(0xFFDC2626),
            icon: const Icon(Icons.delete_outline),
          ),
        ],
      ),
    );
  }
}

class _MetaPill extends StatelessWidget {
  const _MetaPill({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: _line),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 15, color: _muted),
          const SizedBox(width: 6),
          Text(
            label,
            style: Theme.of(context).textTheme.labelMedium?.copyWith(
              color: _ink,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({
    required this.icon,
    required this.title,
    required this.subtitle,
  });

  final IconData icon;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              height: 70,
              width: 70,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: _blue.withValues(alpha: 0.10),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Icon(icon, color: _blue, size: 34),
            ),
            const SizedBox(height: 16),
            Text(
              title,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                color: _ink,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              subtitle,
              textAlign: TextAlign.center,
              style: Theme.of(
                context,
              ).textTheme.bodyMedium?.copyWith(color: _muted, height: 1.35),
            ),
          ],
        ),
      ),
    );
  }
}

class _DashboardSection extends StatelessWidget {
  const _DashboardSection(this.dashboard);

  final dynamic dashboard;

  @override
  Widget build(BuildContext context) {
    final cards = _dashboardCards(dashboard);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const _SectionHeader(
          title: 'Dashboard',
          subtitle: 'A little progress, every day',
        ),
        const SizedBox(height: 10),
        if (cards.isEmpty)
          _DataCard(data: normalizeMap(dashboard))
        else
          LayoutBuilder(
            builder: (context, constraints) {
              final columns = constraints.maxWidth > 760 ? 4 : 2;
              return GridView.builder(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                itemCount: cards.length,
                gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: columns,
                  mainAxisExtent: 144,
                  crossAxisSpacing: 12,
                  mainAxisSpacing: 12,
                ),
                itemBuilder: (context, index) {
                  final card = cards[index];
                  return _MetricCard(
                    label: card.label,
                    value: card.value,
                    icon: _metricIcon(card.label),
                    color: _tileColor(index),
                  );
                },
              );
            },
          ),
      ],
    );
  }
}

class _StudentProgress extends StatelessWidget {
  const _StudentProgress({
    required this.dashboard,
    required this.api,
    required this.token,
  });
  final Map<String, dynamic> dashboard;
  final ApiClient api;
  final String token;
  String percent(dynamic value) => value == null ? '--' : '$value%';
  @override
  Widget build(BuildContext context) {
    final progress = normalizeMap(dashboard['weekly_performance']);
    final current = normalizeMap(progress['current']);
    final weeks = (progress['weeks'] as List? ?? []).map(normalizeMap).toList();
    final sections = (progress['sections'] as List? ?? []).map(normalizeMap);
    final notices = (dashboard['exam_notifications'] as List? ?? []).map(
      normalizeMap,
    );
    final subjects = (dashboard['subject_performance'] as List? ?? [])
        .map(normalizeMap)
        .toList();
    final testedSubjects = subjects
        .where((row) => (row['questions'] as num? ?? 0) > 0)
        .toList();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const _SectionHeader(
          title: 'Weekly performance',
          subtitle: 'Your practice, over the last eight weeks',
        ),
        const SizedBox(height: 20),
        Row(
          children: [
            Expanded(
              child: _ProgressNumber(
                value: '${current['tests'] ?? 0}',
                label: 'Tests this week',
              ),
            ),
            Expanded(
              child: _ProgressNumber(
                value: percent(current['accuracy']),
                label: 'Answer accuracy',
              ),
            ),
            Expanded(
              child: _ProgressNumber(
                value: progress['accuracy_change'] == null
                    ? '--'
                    : '${progress['accuracy_change']} pp',
                label: 'Vs. last week',
              ),
            ),
          ],
        ),
        const SizedBox(height: 25),
        SizedBox(
          height: 162,
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              for (var index = 0; index < weeks.length; index++)
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 4),
                    child: Semantics(
                      label:
                          '${weeks[index]['label']}: ${percent(weeks[index]['accuracy'])}',
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.end,
                        children: [
                          Text(
                            percent(weeks[index]['accuracy']),
                            style: const TextStyle(fontSize: 9, color: _muted),
                          ),
                          const SizedBox(height: 8),
                          Container(
                            height: 112,
                            width: 28,
                            clipBehavior: Clip.antiAlias,
                            decoration: const BoxDecoration(
                              color: Color(0xFFE3E8F3),
                              borderRadius: BorderRadius.vertical(
                                top: Radius.circular(5),
                              ),
                            ),
                            child: Align(
                              alignment: Alignment.bottomCenter,
                              child: FractionallySizedBox(
                                heightFactor:
                                    ((weeks[index]['accuracy'] as num? ?? 0) /
                                            100)
                                        .clamp(0.0, 1.0),
                                child: Container(
                                  color: index == weeks.length - 1
                                      ? _blue
                                      : const Color(0xFFA5B8F3),
                                ),
                              ),
                            ),
                          ),
                          const SizedBox(height: 10),
                          Text(
                            asText(weeks[index]['label']),
                            style: const TextStyle(fontSize: 9, color: _muted),
                            maxLines: 1,
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
            ],
          ),
        ),
        if (current['tests'] == 0)
          const Padding(
            padding: EdgeInsets.only(top: 16),
            child: Text(
              'Your next completed test will bring this week into focus.',
              style: TextStyle(color: _muted, fontSize: 12),
            ),
          ),
        for (final section in sections)
          ListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(asText(section['name'])),
            trailing: Text(percent(section['accuracy'])),
          ),
        const SizedBox(height: 28),
        const _SectionHeader(
          title: 'Subject-wise performance',
          subtitle: 'Every question from your submitted tests',
        ),
        const SizedBox(height: 12),
        if (testedSubjects.isEmpty)
          const Text(
            'Complete a test to see how you are doing in each subject.',
            style: TextStyle(color: _muted, fontSize: 12),
          ),
        for (final row in testedSubjects) _SubjectPerformanceRow(row: row),
        if (testedSubjects.isNotEmpty &&
            subjects.length > testedSubjects.length)
          Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Text(
              subjects.length - testedSubjects.length == 1
                  ? '1 more subject has no test results yet.'
                  : '${subjects.length - testedSubjects.length} more subjects have no test results yet.',
              style: const TextStyle(color: _muted, fontSize: 11),
            ),
          ),
        const SizedBox(height: 28),
        _SectionHeader(
          title: 'Exam notifications',
          subtitle: '${notices.length} pending practice tests',
        ),
        const SizedBox(height: 12),
        if (notices.isEmpty)
          const Text('You are all caught up. New exams will appear here.'),
        for (final notice in notices)
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: _ExamCard(
              api: api,
              token: token,
              exam: {
                ...notice,
                'id': notice['exam_id'],
                'course': {'name': notice['course']},
              },
            ),
          ),
      ],
    );
  }
}

class _ProgressNumber extends StatelessWidget {
  const _ProgressNumber({required this.value, required this.label});
  final String value;
  final String label;
  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        value,
        style: const TextStyle(
          fontSize: 23,
          color: _ink,
          fontWeight: FontWeight.w700,
        ),
      ),
      const SizedBox(height: 5),
      Text(label, style: const TextStyle(fontSize: 10, color: _muted)),
    ],
  );
}

class _ProfileSection extends StatelessWidget {
  const _ProfileSection(this.profile);

  final dynamic profile;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const _SectionHeader(
          title: 'Profile',
          subtitle: 'Current signed-in account',
        ),
        const SizedBox(height: 10),
        _DataCard(data: normalizeMap(profile)),
      ],
    );
  }
}

class _UserHero extends StatelessWidget {
  const _UserHero({required this.name, required this.role});
  final String name;
  final String role;
  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        role == 'student' ? 'YOUR LEARNING, IN FOCUS' : 'YOUR NOVAX WORKSPACE',
        style: const TextStyle(
          fontSize: 10,
          color: _blue,
          fontWeight: FontWeight.w700,
        ),
      ),
      const SizedBox(height: 8),
      Text(
        'Hello, ${name.split(' ').first}.',
        style: Theme.of(context).textTheme.headlineSmall,
      ),
      const SizedBox(height: 5),
      Text(
        role == 'student'
            ? 'What will you discover today?'
            : 'Your institute, at a glance.',
        style: const TextStyle(color: _muted),
      ),
      const SizedBox(height: 22),
      Container(
        constraints: const BoxConstraints(minHeight: 182),
        width: double.infinity,
        decoration: const BoxDecoration(
          image: DecorationImage(
            image: AssetImage('assets/study-desk.jpg'),
            fit: BoxFit.cover,
            alignment: Alignment.centerRight,
          ),
        ),
        child: Align(
          alignment: Alignment.centerLeft,
          child: Container(
            padding: const EdgeInsets.all(20),
            width: 210,
            color: const Color(0xEAECEFF6),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'ONE STEP AT A TIME',
                  style: TextStyle(
                    fontSize: 9,
                    color: _blue,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  role == 'student'
                      ? 'A little practice.\nA bigger future.'
                      : 'Great learning\nstarts here.',
                  style: const TextStyle(
                    fontSize: 23,
                    height: 1.2,
                    fontWeight: FontWeight.w700,
                    color: _ink,
                  ),
                ),
                const SizedBox(height: 14),
                const Text(
                  'Learn. Practice. Progress.',
                  style: TextStyle(fontSize: 11, color: _muted),
                ),
              ],
            ),
          ),
        ),
      ),
    ],
  );
}

class _DataCard extends StatelessWidget {
  const _DataCard({required this.data});

  final Map<String, dynamic> data;

  @override
  Widget build(BuildContext context) {
    final title = firstText(data, const [
      'title',
      'name',
      'email',
      'code',
      'status',
      'type',
    ]);

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: _surface,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: _line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (title != null) ...[
            Text(
              title,
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 8),
          ],
          if (data['year'] != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: Text(
                'Year ${data['year']}',
                style: const TextStyle(
                  fontWeight: FontWeight.w700,
                  color: _blue,
                ),
              ),
            ),
          ...data.entries
              .where(
                (entry) => !const {
                  'download_url',
                  'file_path',
                  'id',
                  'course_id',
                  'subject_id',
                  'uploaded_by',
                  'status',
                  'mime_type',
                  'created_at',
                  'updated_at',
                  'course',
                  'subject',
                }.contains(entry.key),
              )
              .take(8)
              .map(
                (entry) => Padding(
                  padding: const EdgeInsets.only(bottom: 5),
                  child: Text(
                    '${labelize(entry.key)}: ${formatValue(entry.value)}',
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(
                      context,
                    ).textTheme.bodyMedium?.copyWith(color: _muted),
                  ),
                ),
              ),
          if (data['download_url'] != null)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: OutlinedButton.icon(
                onPressed: () => launchUrl(
                  Uri.parse(asText(data['download_url'])),
                  mode: LaunchMode.externalApplication,
                ),
                icon: const Icon(Icons.download_outlined, size: 18),
                label: Text(asText(data['file_name'], fallback: 'Download')),
              ),
            ),
        ],
      ),
    );
  }
}

class _ErrorBox extends StatelessWidget {
  const _ErrorBox({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFFEE2E2),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: const Color(0xFFFCA5A5)),
      ),
      child: Text(message, style: const TextStyle(color: Color(0xFF991B1B))),
    );
  }
}

class ApiClient {
  ApiClient(String baseUrl) : baseUrl = baseUrl.replaceAll(RegExp(r'/+$'), '');

  final String baseUrl;

  Future<Session> login({
    required String email,
    required String password,
  }) async {
    final response = await http.post(
      Uri.parse('$baseUrl/login'),
      headers: const {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({
        'email': email,
        'password': password,
        'device_name': 'flutter',
      }),
    );

    final body = decodeBody(response);
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw ApiException(messageFromBody(body, response.statusCode));
    }

    final map = Map<String, dynamic>.from(body as Map);
    return Session(
      token: asText(map['token']),
      user: Map<String, dynamic>.from(map['user'] as Map),
    );
  }

  Future<dynamic> get(String token, String endpoint) async {
    final response = await http.get(
      Uri.parse('$baseUrl$endpoint'),
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );

    final body = decodeBody(response);
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw ApiException(messageFromBody(body, response.statusCode));
    }
    return body;
  }

  Future<dynamic> post(
    String token,
    String endpoint,
    Map<String, dynamic> payload,
  ) async {
    final response = await http.post(
      Uri.parse('$baseUrl$endpoint'),
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Authorization': 'Bearer $token',
      },
      body: jsonEncode(payload),
    );
    return _decodeMutation(response);
  }

  Future<dynamic> put(
    String token,
    String endpoint,
    Map<String, dynamic> payload,
  ) async {
    final response = await http.put(
      Uri.parse('$baseUrl$endpoint'),
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Authorization': 'Bearer $token',
      },
      body: jsonEncode(payload),
    );
    return _decodeMutation(response);
  }

  Future<dynamic> delete(String token, String endpoint) async {
    final response = await http.delete(
      Uri.parse('$baseUrl$endpoint'),
      headers: {'Accept': 'application/json', 'Authorization': 'Bearer $token'},
    );
    return _decodeMutation(response);
  }

  dynamic _decodeMutation(http.Response response) {
    final body = decodeBody(response);
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw ApiException(messageFromBody(body, response.statusCode));
    }
    return body;
  }
}

class Session {
  const Session({required this.token, required this.user});

  final String token;
  final Map<String, dynamic> user;
}

class HomeData {
  const HomeData({required this.profile, required this.dashboard});

  final dynamic profile;
  final dynamic dashboard;
}

class ApiException implements Exception {
  const ApiException(this.message);

  final String message;

  @override
  String toString() => message;
}

class _ApiLink {
  const _ApiLink(this.title, this.icon, this.endpoint);

  final String title;
  final IconData icon;
  final String endpoint;
}

class ResourceConfig {
  const ResourceConfig({
    required this.title,
    required this.singular,
    required this.endpoint,
    required this.icon,
    required this.fields,
    required this.titleKeys,
    this.canCreate = true,
    this.canDelete = true,
  });

  final String title;
  final String singular;
  final String endpoint;
  final IconData icon;
  final List<ResourceField> fields;
  final List<String> titleKeys;
  final bool canCreate;
  final bool canDelete;

  String subtitle(Map<String, dynamic> item) {
    final values = <String>[];
    for (final key in ['email', 'code', 'status', 'type', 'course_id']) {
      final value =
          item[key] ?? (item['user'] is Map ? item['user'][key] : null);
      if (value != null) values.add('${labelize(key)}: $value');
    }
    return values.isEmpty ? 'ID: ${item['id']}' : values.take(2).join('  ');
  }
}

class ResourceField {
  const ResourceField(
    this.name,
    this.label, {
    this.required = false,
    this.type = FieldType.text,
    this.helper,
    this.maxLines = 1,
  });

  final String name;
  final String label;
  final bool required;
  final FieldType type;
  final String? helper;
  final int maxLines;

  TextInputType get keyboardType {
    return switch (type) {
      FieldType.number => TextInputType.number,
      FieldType.email => TextInputType.emailAddress,
      FieldType.multiline => TextInputType.multiline,
      _ => TextInputType.text,
    };
  }

  dynamic toPayload(String raw) {
    return switch (type) {
      FieldType.number => num.tryParse(raw) ?? raw,
      FieldType.intList =>
        raw
            .split(',')
            .map((value) => int.tryParse(value.trim()))
            .whereType<int>()
            .toList(),
      FieldType.options => {
        'A': optionPart(raw, 0),
        'B': optionPart(raw, 1),
        'C': optionPart(raw, 2),
        'D': optionPart(raw, 3),
        if (optionPart(raw, 4).toString().isNotEmpty) 'E': optionPart(raw, 4),
      },
      _ => raw,
    };
  }
}

enum FieldType { text, email, number, multiline, intList, options }

List<ResourceConfig> resourceConfigs(String role) {
  final staff = [
    ResourceConfig(
      title: 'Students',
      singular: 'Student',
      endpoint: '/management/students',
      icon: Icons.school_outlined,
      titleKeys: const ['name', 'student_id', 'admission_number'],
      fields: const [
        ResourceField('name', 'Name', required: true),
        ResourceField('email', 'Email', required: true, type: FieldType.email),
        ResourceField('mobile', 'Mobile'),
        ResourceField(
          'password',
          'Password',
          helper: 'Required for new student',
        ),
        ResourceField('admission_number', 'Admission number', required: true),
        ResourceField(
          'course_id',
          'Course ID',
          required: true,
          type: FieldType.number,
        ),
        ResourceField('batch_id', 'Batch ID', type: FieldType.number),
        ResourceField('gender', 'Gender'),
        ResourceField('dob', 'DOB', helper: 'YYYY-MM-DD'),
        ResourceField(
          'address',
          'Address',
          type: FieldType.multiline,
          maxLines: 3,
        ),
        ResourceField('father_name', 'Father name'),
        ResourceField('mother_name', 'Mother name'),
        ResourceField('guardian_name', 'Guardian name'),
        ResourceField('parent_mobile', 'Parent mobile'),
        ResourceField('status', 'Status', helper: 'active or inactive'),
      ],
    ),
    ResourceConfig(
      title: 'Questions',
      singular: 'Question',
      endpoint: '/management/questions',
      icon: Icons.quiz_outlined,
      titleKeys: const ['question', 'topic'],
      fields: const [
        ResourceField(
          'course_id',
          'Course ID',
          required: true,
          type: FieldType.number,
        ),
        ResourceField(
          'subject_id',
          'Subject ID',
          required: true,
          type: FieldType.number,
        ),
        ResourceField('exam_pattern_id', 'Pattern ID', type: FieldType.number),
        ResourceField('section_key', 'Section key'),
        ResourceField('language', 'Language', helper: 'english or hindi'),
        ResourceField('type', 'Type', required: true, helper: 'mcq or yes_no'),
        ResourceField(
          'question',
          'Question',
          required: true,
          type: FieldType.multiline,
          maxLines: 4,
        ),
        ResourceField(
          'options',
          'Options',
          type: FieldType.options,
          helper: 'A, B, C, D option text (add a fifth for option E)',
        ),
        ResourceField(
          'correct_answer',
          'Correct answer',
          required: true,
          helper: 'A/B/C/D',
        ),
        ResourceField('difficulty', 'Difficulty', helper: 'easy, medium, hard'),
        ResourceField('marks', 'Marks', type: FieldType.number),
        ResourceField(
          'negative_marks',
          'Negative marks',
          type: FieldType.number,
        ),
        ResourceField('topic', 'Topic'),
        ResourceField(
          'explanation',
          'Explanation',
          type: FieldType.multiline,
          maxLines: 3,
        ),
      ],
    ),
    ResourceConfig(
      title: 'Exams',
      singular: 'Exam',
      endpoint: '/management/exams',
      icon: Icons.assignment_outlined,
      titleKeys: const ['title', 'type'],
      fields: const [
        ResourceField(
          'course_id',
          'Course ID',
          required: true,
          type: FieldType.number,
        ),
        ResourceField(
          'exam_pattern_id',
          'Pattern ID',
          required: true,
          type: FieldType.number,
        ),
        ResourceField('title', 'Title', required: true),
        ResourceField(
          'type',
          'Type',
          required: true,
          helper: 'daily_quiz, weekly_test, monthly_test, mock_test',
        ),
        ResourceField('starts_at', 'Starts at', helper: 'YYYY-MM-DD HH:MM:SS'),
        ResourceField('ends_at', 'Ends at', helper: 'YYYY-MM-DD HH:MM:SS'),
        ResourceField('passing_marks', 'Passing marks', type: FieldType.number),
        ResourceField(
          'dest_passage',
          'Tier 2 typing passage (about 2,000 characters)',
          type: FieldType.multiline,
          maxLines: 8,
        ),
        ResourceField(
          'question_ids',
          'Question IDs',
          type: FieldType.intList,
          helper: 'Comma separated IDs',
        ),
        ResourceField('status', 'Status', helper: 'published, draft, archived'),
      ],
    ),
    ResourceConfig(
      title: 'Attendance',
      singular: 'Attendance',
      endpoint: '/management/attendance',
      icon: Icons.fact_check_outlined,
      titleKeys: const ['attendance_date', 'status'],
      fields: const [
        ResourceField(
          'student_profile_id',
          'Student profile ID',
          required: true,
          type: FieldType.number,
        ),
        ResourceField(
          'course_id',
          'Course ID',
          required: true,
          type: FieldType.number,
        ),
        ResourceField(
          'attendance_date',
          'Date',
          required: true,
          helper: 'YYYY-MM-DD',
        ),
        ResourceField(
          'status',
          'Status',
          required: true,
          helper: 'present, absent, late, leave',
        ),
        ResourceField('remarks', 'Remarks'),
      ],
      canDelete: false,
    ),
    ResourceConfig(
      title: 'Materials',
      singular: 'Material',
      endpoint: '/management/materials',
      icon: Icons.picture_as_pdf_outlined,
      titleKeys: const ['title', 'file_name'],
      fields: const [
        ResourceField(
          'course_id',
          'Course ID',
          required: true,
          type: FieldType.number,
        ),
        ResourceField(
          'subject_id',
          'Subject ID',
          required: true,
          type: FieldType.number,
        ),
        ResourceField('title', 'Title', required: true),
        ResourceField(
          'type',
          'Type',
          required: true,
          helper: 'note, pdf, word, previous_question',
        ),
        ResourceField('year', 'Year', type: FieldType.number),
      ],
    ),
  ];

  if (role != 'super_admin') return staff;

  return [
    staff.first,
    ResourceConfig(
      title: 'Teachers',
      singular: 'Teacher',
      endpoint: '/management/teachers',
      icon: Icons.badge_outlined,
      titleKeys: const ['name', 'employee_id'],
      fields: const [
        ResourceField('name', 'Name', required: true),
        ResourceField('email', 'Email', required: true, type: FieldType.email),
        ResourceField('mobile', 'Mobile'),
        ResourceField(
          'password',
          'Password',
          helper: 'Required for new teacher',
        ),
        ResourceField('employee_id', 'Employee ID', required: true),
        ResourceField('department', 'Department'),
        ResourceField('qualification', 'Qualification'),
        ResourceField(
          'experience_years',
          'Experience years',
          type: FieldType.number,
        ),
        ResourceField(
          'course_ids',
          'Course IDs',
          type: FieldType.intList,
          helper: 'Comma separated IDs',
        ),
        ResourceField('status', 'Status', helper: 'active or inactive'),
      ],
    ),
    ResourceConfig(
      title: 'Courses',
      singular: 'Course',
      endpoint: '/management/courses',
      icon: Icons.menu_book_outlined,
      titleKeys: const ['name', 'code'],
      fields: const [
        ResourceField('name', 'Name', required: true),
        ResourceField('code', 'Code', required: true),
        ResourceField(
          'description',
          'Description',
          type: FieldType.multiline,
          maxLines: 3,
        ),
        ResourceField('duration', 'Duration'),
        ResourceField('status', 'Status', helper: 'active or inactive'),
      ],
    ),
    ResourceConfig(
      title: 'Subjects',
      singular: 'Subject',
      endpoint: '/management/subjects',
      icon: Icons.subject_outlined,
      titleKeys: const ['name', 'code'],
      fields: const [
        ResourceField(
          'course_id',
          'Course ID',
          required: true,
          type: FieldType.number,
        ),
        ResourceField('name', 'Name', required: true),
        ResourceField('code', 'Code'),
        ResourceField(
          'teacher_profile_id',
          'Teacher profile ID',
          type: FieldType.number,
        ),
        ResourceField('status', 'Status', helper: 'active or inactive'),
      ],
    ),
    ...staff.skip(1),
  ];
}

class _DashboardCard {
  const _DashboardCard(this.label, this.value);

  final String label;
  final String value;
}

dynamic decodeBody(http.Response response) {
  final raw = response.body.trim();
  if (raw.isEmpty) return <String, dynamic>{};
  try {
    return jsonDecode(raw);
  } catch (_) {
    return {'message': raw};
  }
}

String messageFromBody(dynamic body, int statusCode) {
  if (body is Map && body['message'] != null) {
    return asText(body['message']);
  }
  if (body is Map && body['errors'] is Map) {
    final errors = body['errors'] as Map;
    if (errors.isNotEmpty) return formatValue(errors.values.first);
  }
  return 'Request failed with status $statusCode';
}

String asText(dynamic value, {String fallback = ''}) {
  if (value == null) return fallback;
  return value.toString();
}

String formatValue(dynamic value) {
  if (value == null) return '-';
  if (value is String || value is num || value is bool) return '$value';
  if (value is List) return '${value.length} items';
  if (value is Map) return '${value.length} fields';
  return value.toString();
}

String? firstText(Map<String, dynamic> data, List<String> keys) {
  for (final key in keys) {
    final value = valueForKey(data, key);
    if (value != null && value.toString().trim().isNotEmpty) {
      return value.toString();
    }
  }
  return null;
}

dynamic valueForKey(Map<String, dynamic> data, String key) {
  if (data.containsKey(key)) return data[key];
  if (data['user'] is Map && (data['user'] as Map).containsKey(key)) {
    return (data['user'] as Map)[key];
  }
  if (data['course'] is Map && (data['course'] as Map).containsKey(key)) {
    return (data['course'] as Map)[key];
  }
  return null;
}

String initialFieldValue(Map<String, dynamic>? item, ResourceField field) {
  if (item == null) return '';
  final value = valueForKey(item, field.name);
  if (value == null) return '';
  if (field.type == FieldType.options && value is Map) {
    return ['A', 'B', 'C', 'D', 'E']
        .map((key) => value[key]?.toString() ?? '')
        .where((part) => part.isNotEmpty)
        .join(', ');
  }
  if (field.type == FieldType.intList && value is List) {
    return value.map((item) => item.toString()).join(', ');
  }
  return value.toString();
}

String optionPart(String raw, int index) {
  final parts = raw.split(',').map((part) => part.trim()).toList();
  if (index >= parts.length) return '';
  return parts[index];
}

String labelize(String key) {
  return key
      .replaceAll('_', ' ')
      .split(' ')
      .where((part) => part.isNotEmpty)
      .map((part) => part[0].toUpperCase() + part.substring(1))
      .join(' ');
}

Map<String, dynamic> normalizeMap(dynamic value) {
  if (value is Map<String, dynamic>) return value;
  if (value is Map) return Map<String, dynamic>.from(value);
  return {'value': value};
}

List<Map<String, dynamic>> extractItems(dynamic body) {
  dynamic value = body;
  if (value is Map && value.containsKey('data')) value = value['data'];
  if (value is Map && value['data'] is List) value = value['data'];

  if (value is List) {
    return value.map((item) => normalizeMap(item)).toList();
  }
  if (value is Map) return [normalizeMap(value)];
  return [];
}

List<_DashboardCard> _dashboardCards(dynamic body) {
  final map = normalizeMap(body);
  final cards = <_DashboardCard>[];
  for (final entry in map.entries) {
    final value = entry.value;
    if (value is num || value is String || value is bool) {
      cards.add(_DashboardCard(labelize(entry.key), formatValue(value)));
    }
  }
  return cards.take(8).toList();
}
