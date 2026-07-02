import CryptoKit
import Foundation
import LocalAuthentication
import Security
import SwiftUI

@MainActor
final class AppLockService: ObservableObject {
    static let shared = AppLockService()

    @Published private(set) var isLocked = false
    @Published private(set) var isUnlockedThisSession = false
    @Published var lockEnabled: Bool {
        didSet { persistBool(Keys.enabled, lockEnabled) }
    }
    @Published var biometricEnabled: Bool {
        didSet { persistBool(Keys.biometric, biometricEnabled) }
    }
    @Published var pinEnabled: Bool {
        didSet { persistBool(Keys.pinEnabled, pinEnabled) }
    }
    @Published var lockOnLaunch: Bool {
        didSet { persistBool(Keys.lockOnLaunch, lockOnLaunch) }
    }
    @Published var autoLockInterval: AutoLockInterval {
        didSet { UserDefaults.standard.set(autoLockInterval.rawValue, forKey: Keys.autoLock) }
    }

    private var lastUnlockedAt = Date()
    private var lastUnlockCompletedAt: Date?
    private var isAuthenticating = false
    private var autoLockSuppressedUntil: Date?
    private let pinService = "at.paxdesign.livechat.applock.pin"
    private let saltService = "at.paxdesign.livechat.applock.salt"

    private let autoLockSuppressInterval: TimeInterval = 3.0
    private let postUnlockGraceInterval: TimeInterval = 1.5

    enum AutoLockInterval: Int, CaseIterable, Identifiable {
        case immediate = 0
        case oneMinute = 60
        case fiveMinutes = 300
        case fifteenMinutes = 900
        case thirtyMinutes = 1800
        case never = -1

        var id: Int { rawValue }

        var label: String {
            switch self {
            case .immediate: return "Sofort"
            case .oneMinute: return "1 Minute"
            case .fiveMinutes: return "5 Minuten"
            case .fifteenMinutes: return "15 Minuten"
            case .thirtyMinutes: return "30 Minuten"
            case .never: return "Nie (nur beim Verlassen)"
            }
        }
    }

    private enum Keys {
        static let enabled = "pax.applock.enabled"
        static let biometric = "pax.applock.biometric"
        static let pinEnabled = "pax.applock.pinEnabled"
        static let lockOnLaunch = "pax.applock.lockOnLaunch"
        static let autoLock = "pax.applock.autoLock"
    }

    private init() {
        let defaults = UserDefaults.standard
        lockEnabled = defaults.bool(forKey: Keys.enabled)
        biometricEnabled = defaults.object(forKey: Keys.biometric) as? Bool ?? true
        pinEnabled = defaults.bool(forKey: Keys.pinEnabled)
        lockOnLaunch = defaults.object(forKey: Keys.lockOnLaunch) as? Bool ?? true
        let raw = defaults.integer(forKey: Keys.autoLock)
        autoLockInterval = AutoLockInterval(rawValue: raw) ?? .fiveMinutes
    }

    var isActive: Bool {
        lockEnabled && (biometricEnabled || pinEnabled)
    }

    var shouldOfferBiometricUnlock: Bool {
        isLocked && biometricEnabled && canUseBiometrics
    }

    var biometricTypeLabel: String {
        let context = LAContext()
        _ = context.canEvaluatePolicy(.deviceOwnerAuthenticationWithBiometrics, error: nil)
        switch context.biometryType {
        case .faceID: return "Face ID"
        case .touchID: return "Touch ID"
        case .opticID: return "Optic ID"
        default: return "Biometrie"
        }
    }

    var canUseBiometrics: Bool {
        let context = LAContext()
        return context.canEvaluatePolicy(.deviceOwnerAuthenticationWithBiometrics, error: nil)
    }

    func prepareForLogin() {
        isUnlockedThisSession = false
        isAuthenticating = false
        autoLockSuppressedUntil = nil
        if isActive && lockOnLaunch {
            isLocked = true
        }
    }

    func lock() {
        guard isActive, !shouldSuppressAutoLock() else { return }
        isLocked = true
        isUnlockedThisSession = false
    }

    func unlock() {
        isLocked = false
        isUnlockedThisSession = true
        isAuthenticating = false
        let now = Date()
        lastUnlockedAt = now
        lastUnlockCompletedAt = now
        extendAutoLockSuppression(for: postUnlockGraceInterval)
    }

    func recordActivity() {
        lastUnlockedAt = Date()
    }

    func handleScenePhase(_ phase: ScenePhase, isLoggedIn: Bool) {
        guard isLoggedIn, isActive else { return }

        switch phase {
        case .background:
            guard !shouldSuppressAutoLock() else { return }
            if autoLockInterval == .immediate {
                lock()
            }
        case .inactive:
            // Do not lock while inactive. Face ID and system sheets move the app here.
            break
        case .active:
            guard !shouldSuppressAutoLock() else { return }
            if shouldLockDueToInactivity() {
                lock()
            }
        @unknown default:
            break
        }
    }

    func shouldLockDueToInactivity() -> Bool {
        guard isActive, autoLockInterval != .never else { return false }
        let elapsed = Date().timeIntervalSince(lastUnlockedAt)
        return elapsed >= TimeInterval(autoLockInterval.rawValue)
    }

    /// Presents the native Face ID / Touch ID sheet once. Ignores duplicate calls while in flight.
    func requestBiometricUnlockIfNeeded() async {
        guard shouldOfferBiometricUnlock else { return }
        guard !isAuthenticating else { return }

        isAuthenticating = true
        extendAutoLockSuppression(for: autoLockSuppressInterval)

        defer { isAuthenticating = false }

        do {
            let context = LAContext()
            context.localizedCancelTitle = "Abbrechen"
            let success = try await context.evaluatePolicy(
                .deviceOwnerAuthenticationWithBiometrics,
                localizedReason: "PAXDesign Live Chat entsperren"
            )
            if success {
                unlock()
            }
        } catch {
            // Cancellation or failure — stay on lock screen; PIN remains available.
        }
    }

    /// User-initiated native device authentication (Face ID, Touch ID, or device passcode).
    func requestDeviceAuthentication() async -> Bool {
        guard isLocked else { return false }
        guard !isAuthenticating else { return false }

        isAuthenticating = true
        extendAutoLockSuppression(for: autoLockSuppressInterval)

        defer { isAuthenticating = false }

        do {
            let context = LAContext()
            context.localizedCancelTitle = "Abbrechen"
            let success = try await context.evaluatePolicy(
                .deviceOwnerAuthentication,
                localizedReason: "PAXDesign Live Chat entsperren"
            )
            if success {
                unlock()
            }
            return success
        } catch {
            return false
        }
    }

    func hasPINConfigured() -> Bool {
        KeychainHelper.read(service: pinService) != nil
    }

    func setPIN(_ pin: String) throws {
        let trimmed = pin.trimmingCharacters(in: .whitespacesAndNewlines)
        guard trimmed.count >= 4, trimmed.count <= 8, trimmed.allSatisfy(\.isNumber) else {
            throw AppLockError.invalidPIN
        }
        let salt = randomSalt()
        let hash = hashPIN(trimmed, salt: salt)
        KeychainHelper.save(Data(hash.utf8), service: pinService)
        KeychainHelper.save(Data(salt.utf8), service: saltService)
        pinEnabled = true
    }

    func removePIN() {
        KeychainHelper.delete(service: pinService)
        KeychainHelper.delete(service: saltService)
        pinEnabled = false
    }

    func verifyPIN(_ pin: String) -> Bool {
        guard let hashData = KeychainHelper.read(service: pinService),
              let saltData = KeychainHelper.read(service: saltService),
              let stored = String(data: hashData, encoding: .utf8),
              let salt = String(data: saltData, encoding: .utf8) else {
            return false
        }
        return hashPIN(pin, salt: salt) == stored
    }

    private func shouldSuppressAutoLock() -> Bool {
        if isAuthenticating { return true }
        if let until = autoLockSuppressedUntil, Date() < until { return true }
        if let completed = lastUnlockCompletedAt,
           Date().timeIntervalSince(completed) < postUnlockGraceInterval {
            return true
        }
        return false
    }

    private func extendAutoLockSuppression(for interval: TimeInterval) {
        let until = Date().addingTimeInterval(interval)
        if let existing = autoLockSuppressedUntil {
            autoLockSuppressedUntil = max(existing, until)
        } else {
            autoLockSuppressedUntil = until
        }
    }

    private func hashPIN(_ pin: String, salt: String) -> String {
        let input = Data((salt + pin).utf8)
        let digest = SHA256.hash(data: input)
        return digest.map { String(format: "%02x", $0) }.joined()
    }

    private func randomSalt() -> String {
        UUID().uuidString.replacingOccurrences(of: "-", with: "")
    }

    private func persistBool(_ key: String, _ value: Bool) {
        UserDefaults.standard.set(value, forKey: key)
    }
}

enum AppLockError: LocalizedError {
    case invalidPIN
    case keychainFailed
    case pinMismatch

    var errorDescription: String? {
        switch self {
        case .invalidPIN: return "PIN muss 4–8 Ziffern enthalten."
        case .keychainFailed: return "PIN konnte nicht gespeichert werden."
        case .pinMismatch: return "PINs stimmen nicht überein."
        }
    }
}
