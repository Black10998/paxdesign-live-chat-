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

        var id: String { rawValue }

        var title: String {
            switch self {
            case .classic: return "Classic"
            case .chime: return "Chime"
            case .pulse: return "Pulse"
            case .bell: return "Bell"
            case .digital: return "Digital"
            }
        }
    }

    @Published var appearanceMode: AppearanceMode {
        didSet { DeferredUserDefaults.set(appearanceMode.rawValue, forKey: Keys.appearance) }
    }
    @Published var languageMode: LanguageMode {
        didSet { DeferredUserDefaults.set(languageMode.rawValue, forKey: Keys.language) }
    }
    @Published var visualTheme: VisualTheme {
        didSet { DeferredUserDefaults.set(visualTheme.rawValue, forKey: Keys.visualTheme) }
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
    @Published var accentColorPreset: AccentColorPreset {
        didSet { DeferredUserDefaults.set(accentColorPreset.rawValue, forKey: Keys.accentPreset) }
    }

    /// Resolved theme palette including optional accent override.
    var palette: PAXThemePalette {
        effectivePalette
    }

    var resolvedLocale: Locale {
        if let id = languageMode.localeIdentifier {
            return Locale(identifier: id)
        }
        return Locale.autoupdatingCurrent
    }

    private var readPersistTask: Task<Void, Never>?

    func markSessionRead(_ sessionId: String) {
        guard readSessionIds.insert(sessionId).inserted else { return }
    }

    func markSessionUnread(_ sessionId: String) {
        guard readSessionIds.remove(sessionId) != nil else { return }
    }

    private func scheduleReadSessionPersist() {
        readPersistTask?.cancel()
        readPersistTask = Task { [readSessionIds] in
            try? await Task.sleep(nanoseconds: 400_000_000)
            guard !Task.isCancelled else { return }
            DeferredUserDefaults.set(Array(readSessionIds), forKey: Keys.readSessions, delayNanoseconds: 0)
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
        static let compactList = "pax.settings.compactList"
        static let showTimestamps = "pax.settings.showTimestamps"
        static let privacyBanner = "pax.settings.privacyBanner"
        static let volume = "pax.settings.ringVolume"
        static let profileImage = "pax.settings.profileImage"
        static let onboarding = "pax.settings.onboardingCompleted"
        static let firstLaunchOnboarding = "pax.firstLaunch.onboardingCompleted"
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
        compactListMode = defaults.object(forKey: Keys.compactList) as? Bool ?? false
        showListTimestamps = defaults.object(forKey: Keys.showTimestamps) as? Bool ?? true
        ringtoneVolume = defaults.object(forKey: Keys.volume) as? Float ?? 0.9
        profileImageData = defaults.data(forKey: Keys.profileImage)
        onboardingCompleted = defaults.object(forKey: Keys.onboarding) as? Bool ?? false
        firstLaunchOnboardingCompleted = defaults.object(forKey: Keys.firstLaunchOnboarding) as? Bool ?? false
        if let raw = defaults.string(forKey: Keys.accentPreset),
           let preset = AccentColorPreset(rawValue: raw) {
            accentColorPreset = preset
        } else {
            accentColorPreset = .themeDefault
        }
    }
}
