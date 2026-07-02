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

    @Published var appearanceMode: AppearanceMode {
        didSet { UserDefaults.standard.set(appearanceMode.rawValue, forKey: Keys.appearance) }
    }
    @Published var notificationsEnabled: Bool {
        didSet { UserDefaults.standard.set(notificationsEnabled, forKey: Keys.notifications) }
    }
    @Published var incomingCallSoundEnabled: Bool {
        didSet { UserDefaults.standard.set(incomingCallSoundEnabled, forKey: Keys.incomingSound) }
    }
    @Published var messageSoundEnabled: Bool {
        didSet { UserDefaults.standard.set(messageSoundEnabled, forKey: Keys.messageSound) }
    }
    @Published var typingSoundEnabled: Bool {
        didSet { UserDefaults.standard.set(typingSoundEnabled, forKey: Keys.typingSound) }
    }
    @Published var privacyBannerDismissed: Bool {
        didSet { UserDefaults.standard.set(privacyBannerDismissed, forKey: Keys.privacyBanner) }
    }
    @Published var ringtoneVolume: Float {
        didSet { UserDefaults.standard.set(ringtoneVolume, forKey: Keys.volume) }
    }
    @Published var sendSoundEnabled: Bool {
        didSet { UserDefaults.standard.set(sendSoundEnabled, forKey: Keys.sendSound) }
    }
    @Published var readSessionIds: Set<String> {
        didSet {
            UserDefaults.standard.set(Array(readSessionIds), forKey: Keys.readSessions)
        }
    }
    @Published var profileImageData: Data? {
        didSet { UserDefaults.standard.set(profileImageData, forKey: Keys.profileImage) }
    }

    private enum Keys {
        static let appearance = "pax.settings.appearance"
        static let notifications = "pax.settings.notifications"
        static let incomingSound = "pax.settings.incomingSound"
        static let messageSound = "pax.settings.messageSound"
        static let typingSound = "pax.settings.typingSound"
        static let sendSound = "pax.settings.sendSound"
        static let readSessions = "pax.settings.readSessions"
        static let privacyBanner = "pax.settings.privacyBanner"
        static let volume = "pax.settings.ringVolume"
        static let profileImage = "pax.settings.profileImage"
    }

    init() {
        let defaults = UserDefaults.standard
        if let raw = defaults.string(forKey: Keys.appearance),
           let mode = AppearanceMode(rawValue: raw) {
            appearanceMode = mode
        } else {
            appearanceMode = .system
        }
        notificationsEnabled = defaults.object(forKey: Keys.notifications) as? Bool ?? true
        incomingCallSoundEnabled = defaults.object(forKey: Keys.incomingSound) as? Bool ?? true
        messageSoundEnabled = defaults.object(forKey: Keys.messageSound) as? Bool ?? true
        typingSoundEnabled = defaults.object(forKey: Keys.typingSound) as? Bool ?? true
        sendSoundEnabled = defaults.object(forKey: Keys.sendSound) as? Bool ?? true
        privacyBannerDismissed = defaults.object(forKey: Keys.privacyBanner) as? Bool ?? false
        if let read = defaults.array(forKey: Keys.readSessions) as? [String] {
            readSessionIds = Set(read)
        } else {
            readSessionIds = []
        }
        ringtoneVolume = defaults.object(forKey: Keys.volume) as? Float ?? 0.9
        profileImageData = defaults.data(forKey: Keys.profileImage)
    }
}
