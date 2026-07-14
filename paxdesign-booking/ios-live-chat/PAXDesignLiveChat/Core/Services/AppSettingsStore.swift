import Foundation
import SwiftUI

@MainActor
final class AppSettingsStore: ObservableObject {
    static let shared = AppSettingsStore()

    enum AppearanceMode: String, CaseIterable, Identifiable {
        case system
        case light
        case dark

        var id: String { rawValue }

        var title: String {
            switch self {
            case .system: return L10n.AppearanceSystem
            case .light: return L10n.AppearanceLight
            case .dark: return L10n.AppearanceDark
            }
        }

        var colorScheme: ColorScheme? {
            switch self {
            case .system: return nil
            case .light: return .light
            case .dark: return .dark
            }
        }
    }

    enum LanguageMode: String, CaseIterable, Identifiable {
        case system
        case en
        case de
        case ar

        var id: String { rawValue }

        var title: String {
            switch self {
            case .system: return L10n.LanguageSystem
            case .en: return L10n.LanguageEnglish
            case .de: return L10n.LanguageGerman
            case .ar: return L10n.LanguageArabic
            }
        }

        var localeIdentifier: String? {
            switch self {
            case .system: return nil
            case .en: return "en"
            case .de: return "de"
            case .ar: return "ar"
            }
        }

        var layoutDirectionOverride: LayoutDirection? {
            switch self {
            case .system: return nil
            case .ar: return .rightToLeft
            case .en, .de: return .leftToRight
            }
        }
    }

    enum VisualTheme: String, CaseIterable, Identifiable {
        case classic
        case aurora
        case midnight
        case ocean
        case rosegold
        case forest

        var id: String { rawValue }

        var title: String {
            switch self {
            case .classic: return L10n.ThemeClassic
            case .aurora: return L10n.ThemeAurora
            case .midnight: return L10n.ThemeMidnight
            case .ocean: return L10n.ThemeOcean
            case .rosegold: return L10n.ThemeRosegold
            case .forest: return L10n.ThemeForest
            }
        }

        var subtitle: String {
            switch self {
            case .classic: return L10n.ThemeClassicSubtitle
            case .aurora: return L10n.ThemeAuroraSubtitle
            case .midnight: return L10n.ThemeMidnightSubtitle
            case .ocean: return L10n.ThemeOceanSubtitle
            case .rosegold: return L10n.ThemeRosegoldSubtitle
            case .forest: return L10n.ThemeForestSubtitle
            }
        }
    }

    enum NotificationToneStyle: String, CaseIterable, Identifiable {
        case classic
        case chime
        case pulse
        case bell
        case digital
        case soft
        case echo

        var id: String { rawValue }

        var title: String {
            switch self {
            case .classic: return L10n.SoundToneClassic
            case .chime: return L10n.SoundToneChime
            case .pulse: return L10n.SoundTonePulse
            case .bell: return L10n.SoundToneBell
            case .digital: return L10n.SoundToneDigital
            case .soft: return L10n.SoundToneSoft
            case .echo: return L10n.SoundToneEcho
            }
        }
    }

    @Published var appearanceMode: AppearanceMode {
        didSet {
            DeferredUserDefaults.set(appearanceMode.rawValue, forKey: Keys.appearance)
            bumpThemeRevision()
        }
    }
    @Published var languageMode: LanguageMode {
        didSet { DeferredUserDefaults.set(languageMode.rawValue, forKey: Keys.language) }
    }
    @Published var visualTheme: VisualTheme {
        didSet {
            DeferredUserDefaults.set(visualTheme.rawValue, forKey: Keys.visualTheme)
            bumpThemeRevision()
        }
    }
    @Published var aiSuggestionsEnabled: Bool {
        didSet { DeferredUserDefaults.set(aiSuggestionsEnabled, forKey: Keys.aiSuggestions) }
    }
    @Published var notificationsEnabled: Bool {
        didSet { DeferredUserDefaults.set(notificationsEnabled, forKey: Keys.notifications) }
    }
    @Published var incomingCallSoundEnabled: Bool {
        didSet { DeferredUserDefaults.set(incomingCallSoundEnabled, forKey: Keys.incomingSound) }
    }
    @Published var messageSoundEnabled: Bool {
        didSet { DeferredUserDefaults.set(messageSoundEnabled, forKey: Keys.messageSound) }
    }
    @Published var typingSoundEnabled: Bool {
        didSet { DeferredUserDefaults.set(typingSoundEnabled, forKey: Keys.typingSound) }
    }
    @Published var privacyBannerDismissed: Bool {
        didSet { DeferredUserDefaults.set(privacyBannerDismissed, forKey: Keys.privacyBanner) }
    }
    @Published var ringtoneVolume: Float {
        didSet { DeferredUserDefaults.set(ringtoneVolume, forKey: Keys.volume) }
    }
    @Published var sendSoundEnabled: Bool {
        didSet { DeferredUserDefaults.set(sendSoundEnabled, forKey: Keys.sendSound) }
    }
    @Published var messageToneStyle: NotificationToneStyle {
        didSet { DeferredUserDefaults.set(messageToneStyle.rawValue, forKey: Keys.messageToneStyle) }
    }
    @Published var liveToneStyle: NotificationToneStyle {
        didSet { DeferredUserDefaults.set(liveToneStyle.rawValue, forKey: Keys.liveToneStyle) }
    }
    @Published var aiToneStyle: NotificationToneStyle {
        didSet { DeferredUserDefaults.set(aiToneStyle.rawValue, forKey: Keys.aiToneStyle) }
    }
    @Published var sendToneStyle: NotificationToneStyle {
        didSet { DeferredUserDefaults.set(sendToneStyle.rawValue, forKey: Keys.sendToneStyle) }
    }
    @Published var typingToneStyle: NotificationToneStyle {
        didSet { DeferredUserDefaults.set(typingToneStyle.rawValue, forKey: Keys.typingToneStyle) }
    }
    @Published var readSessionIds: Set<String> {
        didSet {
            scheduleReadSessionPersist()
        }
    }
    @Published var readUpToSeq: [String: Int] {
        didSet {
            scheduleReadSeqPersist()
        }
    }
    /// User explicitly marked unread — survives admin-last-reply and read-session state.
    @Published var forcedUnreadSessionIds: Set<String> {
        didSet { scheduleForcedUnreadPersist() }
    }
    @Published var archivedSessionIds: Set<String> {
        didSet { scheduleArchivedPersist() }
    }
    @Published var pinnedSessionIds: Set<String> {
        didSet { schedulePinnedPersist() }
    }
    @Published var mutedSessionIds: Set<String> {
        didSet { scheduleMutedPersist() }
    }
    @Published var compactListMode: Bool {
        didSet { DeferredUserDefaults.set(compactListMode, forKey: Keys.compactList) }
    }
    @Published var showListTimestamps: Bool {
        didSet { DeferredUserDefaults.set(showListTimestamps, forKey: Keys.showTimestamps) }
    }
    @Published var profileImageData: Data? {
        didSet { DeferredUserDefaults.setData(profileImageData, forKey: Keys.profileImage) }
    }
    @Published var onboardingCompleted: Bool {
        didSet { DeferredUserDefaults.set(onboardingCompleted, forKey: Keys.onboarding) }
    }
    /// Shown once after a fresh install — cleared when the app is deleted and reinstalled.
    @Published var firstLaunchOnboardingCompleted: Bool {
        didSet { DeferredUserDefaults.set(firstLaunchOnboardingCompleted, forKey: Keys.firstLaunchOnboarding) }
    }
    @Published var dashboardTourCompleted: Bool {
        didSet { DeferredUserDefaults.set(dashboardTourCompleted, forKey: Keys.dashboardTourCompleted) }
    }
    @Published var accentColorPreset: AccentColorPreset {
        didSet {
            DeferredUserDefaults.set(accentColorPreset.rawValue, forKey: Keys.accentPreset)
            bumpThemeRevision()
        }
    }

    /// Bumped whenever theme, accent, or appearance mode changes to force shell refresh.
    @Published private(set) var themeRevision = UUID()

    /// Resolved theme palette including optional accent override.
    var palette: PAXThemePalette {
        effectivePalette
    }

    func resolvedIsDark(for colorScheme: ColorScheme) -> Bool {
        switch appearanceMode {
        case .dark: return true
        case .light: return false
        case .system: return colorScheme == .dark
        }
    }

    private func bumpThemeRevision() {
        let isDark: Bool
        switch appearanceMode {
        case .dark: isDark = true
        case .light: isDark = false
        case .system:
            isDark = UITraitCollection.current.userInterfaceStyle == .dark
        }
        PAXTheme.applyPalette(effectivePalette, isDark: isDark)
        themeRevision = UUID()
    }

    var resolvedLocale: Locale {
        if let id = languageMode.localeIdentifier {
            return Locale(identifier: id)
        }
        return Locale.autoupdatingCurrent
    }

    private var readPersistTask: Task<Void, Never>?
    private var readSeqPersistTask: Task<Void, Never>?
    private var forcedUnreadPersistTask: Task<Void, Never>?
    private var archivedPersistTask: Task<Void, Never>?
    private var pinnedPersistTask: Task<Void, Never>?
    private var mutedPersistTask: Task<Void, Never>?

    func isSessionUnread(_ session: LiveSession) -> Bool {
        if forcedUnreadSessionIds.contains(session.sessionId) {
            return true
        }
        return unreadMessageCount(for: session) > 0
    }

    func isSessionArchived(_ sessionId: String) -> Bool {
        archivedSessionIds.contains(sessionId)
    }

    func isSessionPinned(_ sessionId: String) -> Bool {
        pinnedSessionIds.contains(sessionId)
    }

    func isSessionMuted(_ sessionId: String) -> Bool {
        mutedSessionIds.contains(sessionId)
    }

    func archiveSession(_ sessionId: String) {
        archivedSessionIds.insert(sessionId)
    }

    func unarchiveSession(_ sessionId: String) {
        archivedSessionIds.remove(sessionId)
    }

    func setSessionPinned(_ sessionId: String, pinned: Bool) {
        if pinned {
            pinnedSessionIds.insert(sessionId)
        } else {
            pinnedSessionIds.remove(sessionId)
        }
    }

    func setSessionMuted(_ sessionId: String, muted: Bool) {
        if muted {
            mutedSessionIds.insert(sessionId)
        } else {
            mutedSessionIds.remove(sessionId)
        }
    }

    /// Number of unread incoming messages in a session (not just conversation count).
    func unreadMessageCount(for session: LiveSession) -> Int {
        guard !session.isClosed else { return 0 }
        let readSeq = resolvedReadSeq(for: session)
        guard session.seq > readSeq else { return 0 }

        if session.isTeamDM {
            return session.lastRole == "user" ? max(0, session.seq - readSeq) : 0
        }

        guard session.needsReply || session.lastRole == "user" else { return 0 }
        return max(0, session.seq - readSeq)
    }

    private func resolvedReadSeq(for session: LiveSession) -> Int {
        if let readSeq = readUpToSeq[session.sessionId] {
            return readSeq
        }
        if readSessionIds.contains(session.sessionId) {
            return session.seq
        }
        return 0
    }

    func markSessionRead(_ sessionId: String, seq: Int? = nil) {
        forcedUnreadSessionIds.remove(sessionId)
        if let seq {
            let current = readUpToSeq[sessionId] ?? 0
            if seq > current {
                readUpToSeq[sessionId] = seq
            }
        }
        readSessionIds.insert(sessionId)
    }

    func markSessionUnread(_ sessionId: String) {
        readUpToSeq.removeValue(forKey: sessionId)
        readSessionIds.remove(sessionId)
        forcedUnreadSessionIds.insert(sessionId)
    }

    private func scheduleReadSessionPersist() {
        readPersistTask?.cancel()
        readPersistTask = Task { [readSessionIds] in
            try? await Task.sleep(nanoseconds: 400_000_000)
            guard !Task.isCancelled else { return }
            DeferredUserDefaults.set(Array(readSessionIds), forKey: Keys.readSessions, delayNanoseconds: 0)
        }
    }

    private func scheduleReadSeqPersist() {
        readSeqPersistTask?.cancel()
        readSeqPersistTask = Task { [readUpToSeq] in
            try? await Task.sleep(nanoseconds: 400_000_000)
            guard !Task.isCancelled else { return }
            DeferredUserDefaults.set(readUpToSeq, forKey: Keys.readUpToSeq, delayNanoseconds: 0)
        }
    }

    private func scheduleForcedUnreadPersist() {
        forcedUnreadPersistTask?.cancel()
        forcedUnreadPersistTask = Task { [forcedUnreadSessionIds] in
            try? await Task.sleep(nanoseconds: 400_000_000)
            guard !Task.isCancelled else { return }
            DeferredUserDefaults.set(Array(forcedUnreadSessionIds), forKey: Keys.forcedUnreadSessions, delayNanoseconds: 0)
        }
    }

    private func scheduleArchivedPersist() {
        archivedPersistTask?.cancel()
        archivedPersistTask = Task { [archivedSessionIds] in
            try? await Task.sleep(nanoseconds: 400_000_000)
            guard !Task.isCancelled else { return }
            DeferredUserDefaults.set(Array(archivedSessionIds), forKey: Keys.archivedSessions, delayNanoseconds: 0)
        }
    }

    private func schedulePinnedPersist() {
        pinnedPersistTask?.cancel()
        pinnedPersistTask = Task { [pinnedSessionIds] in
            try? await Task.sleep(nanoseconds: 400_000_000)
            guard !Task.isCancelled else { return }
            DeferredUserDefaults.set(Array(pinnedSessionIds), forKey: Keys.pinnedSessions, delayNanoseconds: 0)
        }
    }

    private func scheduleMutedPersist() {
        mutedPersistTask?.cancel()
        mutedPersistTask = Task { [mutedSessionIds] in
            try? await Task.sleep(nanoseconds: 400_000_000)
            guard !Task.isCancelled else { return }
            DeferredUserDefaults.set(Array(mutedSessionIds), forKey: Keys.mutedSessions, delayNanoseconds: 0)
        }
    }

    private enum Keys {
        static let appearance = "pax.settings.appearance"
        static let language = "pax.settings.language"
        static let visualTheme = "pax.settings.visualTheme"
        static let aiSuggestions = "pax.settings.aiSuggestions"
        static let notifications = "pax.settings.notifications"
        static let incomingSound = "pax.settings.incomingSound"
        static let messageSound = "pax.settings.messageSound"
        static let typingSound = "pax.settings.typingSound"
        static let sendSound = "pax.settings.sendSound"
        static let readSessions = "pax.settings.readSessions"
        static let readUpToSeq = "pax.settings.readUpToSeq"
        static let forcedUnreadSessions = "pax.settings.forcedUnreadSessions"
        static let archivedSessions = "pax.settings.archivedSessions"
        static let pinnedSessions = "pax.settings.pinnedSessions"
        static let mutedSessions = "pax.settings.mutedSessions"
        static let compactList = "pax.settings.compactList"
        static let showTimestamps = "pax.settings.showTimestamps"
        static let privacyBanner = "pax.settings.privacyBanner"
        static let volume = "pax.settings.ringVolume"
        static let profileImage = "pax.settings.profileImage"
        static let onboarding = "pax.settings.onboardingCompleted"
        static let firstLaunchOnboarding = "pax.firstLaunch.onboardingCompleted"
        static let dashboardTourCompleted = "pax.settings.dashboardTourCompleted"
        static let accentPreset = "pax.settings.accentPreset"
        static let messageToneStyle = "pax.settings.messageToneStyle"
        static let liveToneStyle = "pax.settings.liveToneStyle"
        static let aiToneStyle = "pax.settings.aiToneStyle"
        static let sendToneStyle = "pax.settings.sendToneStyle"
        static let typingToneStyle = "pax.settings.typingToneStyle"
    }

    init() {
        let defaults = UserDefaults.standard
        if let raw = defaults.string(forKey: Keys.appearance),
           let mode = AppearanceMode(rawValue: raw) {
            appearanceMode = mode
        } else {
            appearanceMode = .system
        }
        if let raw = defaults.string(forKey: Keys.language),
           let mode = LanguageMode(rawValue: raw) {
            languageMode = mode
        } else {
            languageMode = .system
        }
        if let raw = defaults.string(forKey: Keys.visualTheme),
           let theme = VisualTheme(rawValue: raw) {
            visualTheme = theme
        } else {
            visualTheme = .classic
        }
        aiSuggestionsEnabled = defaults.object(forKey: Keys.aiSuggestions) as? Bool ?? true
        notificationsEnabled = defaults.object(forKey: Keys.notifications) as? Bool ?? true
        incomingCallSoundEnabled = defaults.object(forKey: Keys.incomingSound) as? Bool ?? true
        messageSoundEnabled = defaults.object(forKey: Keys.messageSound) as? Bool ?? true
        typingSoundEnabled = defaults.object(forKey: Keys.typingSound) as? Bool ?? true
        sendSoundEnabled = defaults.object(forKey: Keys.sendSound) as? Bool ?? true
        if let raw = defaults.string(forKey: Keys.messageToneStyle), let tone = NotificationToneStyle(rawValue: raw) {
            messageToneStyle = tone
        } else {
            messageToneStyle = .classic
        }
        if let raw = defaults.string(forKey: Keys.liveToneStyle), let tone = NotificationToneStyle(rawValue: raw) {
            liveToneStyle = tone
        } else {
            liveToneStyle = .bell
        }
        if let raw = defaults.string(forKey: Keys.aiToneStyle), let tone = NotificationToneStyle(rawValue: raw) {
            aiToneStyle = tone
        } else {
            aiToneStyle = .digital
        }
        if let raw = defaults.string(forKey: Keys.sendToneStyle), let tone = NotificationToneStyle(rawValue: raw) {
            sendToneStyle = tone
        } else {
            sendToneStyle = .chime
        }
        if let raw = defaults.string(forKey: Keys.typingToneStyle), let tone = NotificationToneStyle(rawValue: raw) {
            typingToneStyle = tone
        } else {
            typingToneStyle = .pulse
        }
        privacyBannerDismissed = defaults.object(forKey: Keys.privacyBanner) as? Bool ?? false
        if let read = defaults.array(forKey: Keys.readSessions) as? [String] {
            readSessionIds = Set(read)
        } else {
            readSessionIds = []
        }
        if let seqMap = defaults.dictionary(forKey: Keys.readUpToSeq) as? [String: Int] {
            readUpToSeq = seqMap
        } else if let seqMap = defaults.dictionary(forKey: Keys.readUpToSeq) as? [String: NSNumber] {
            readUpToSeq = seqMap.mapValues { $0.intValue }
        } else {
            readUpToSeq = [:]
        }
        if let forced = defaults.array(forKey: Keys.forcedUnreadSessions) as? [String] {
            forcedUnreadSessionIds = Set(forced)
        } else {
            forcedUnreadSessionIds = []
        }
        if let archived = defaults.array(forKey: Keys.archivedSessions) as? [String] {
            archivedSessionIds = Set(archived)
        } else {
            archivedSessionIds = []
        }
        if let pinned = defaults.array(forKey: Keys.pinnedSessions) as? [String] {
            pinnedSessionIds = Set(pinned)
        } else {
            pinnedSessionIds = []
        }
        if let muted = defaults.array(forKey: Keys.mutedSessions) as? [String] {
            mutedSessionIds = Set(muted)
        } else {
            mutedSessionIds = []
        }
        compactListMode = defaults.object(forKey: Keys.compactList) as? Bool ?? false
        showListTimestamps = defaults.object(forKey: Keys.showTimestamps) as? Bool ?? true
        ringtoneVolume = defaults.object(forKey: Keys.volume) as? Float ?? 0.9
        profileImageData = defaults.data(forKey: Keys.profileImage)
        onboardingCompleted = defaults.object(forKey: Keys.onboarding) as? Bool ?? false
        firstLaunchOnboardingCompleted = defaults.object(forKey: Keys.firstLaunchOnboarding) as? Bool ?? false
        dashboardTourCompleted = defaults.object(forKey: Keys.dashboardTourCompleted) as? Bool ?? false
        if let raw = defaults.string(forKey: Keys.accentPreset),
           let preset = AccentColorPreset(rawValue: raw) {
            accentColorPreset = preset
        } else {
            accentColorPreset = .themeDefault
        }
        bumpThemeRevision()
    }
}
