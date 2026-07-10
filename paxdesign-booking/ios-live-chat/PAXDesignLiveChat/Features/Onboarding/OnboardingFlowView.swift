import SwiftUI
import UserNotifications
import CoreLocation
import UIKit

struct OnboardingPage: Identifiable {
    let id = UUID()
    let title: String
    let subtitle: String
    let systemImage: String
}

private enum OnboardingPasswordField: Hashable {
    case password
    case confirm
}

struct OnboardingFlowView: View {
    enum Mode {
        case firstLaunch
        case postLogin
    }

    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var settings: AppSettingsStore
    @EnvironmentObject private var push: PushService
    @Environment(\.scenePhase) private var scenePhase
    @ObservedObject private var permissions = PermissionCoordinator.shared
    @ObservedObject private var locationPermission = LocationPermissionService.shared
    @ObservedObject private var appLock = AppLockService.shared

    @State private var pageIndex = 0
    @State private var acceptedTerms = false
    @State private var notificationsRequestInFlight = false
    @State private var locationRequestInFlight = false
    @State private var biometricRequestInFlight = false
    @State private var completionError: String?
    @State private var isCompleting = false
    @State private var securityPassword = ""
    @State private var securityPasswordConfirm = ""
    @State private var enableBiometricProtection = true
    @State private var biometricVerified = false
    @FocusState private var focusedPasswordField: OnboardingPasswordField?

    let mode: Mode
    let onComplete: () -> Void

    init(mode: Mode = .postLogin, onComplete: @escaping () -> Void) {
        self.mode = mode
        self.onComplete = onComplete
    }

    private var pages: [OnboardingPage] {
        [
            OnboardingPage(
                title: "Willkommen bei PAXDesign Live Chat",
                subtitle: "Ihre professionelle Plattform für Kundenkommunikation, Teamarbeit und Live-Support.",
                systemImage: "bubble.left.and.bubble.right.fill"
            ),
            OnboardingPage(
                title: "Chat-Übersicht",
                subtitle: "Alle Kundengespräche auf einen Blick. Filtern, suchen und ungelesene Nachrichten sofort erkennen.",
                systemImage: "list.bullet.rectangle.portrait.fill"
            ),
            OnboardingPage(
                title: "Team-Messaging",
                subtitle: "Kommunizieren Sie intern mit Ihrem Team — direkt integriert in die Chat-Liste.",
                systemImage: "person.3.fill"
            ),
            OnboardingPage(
                title: "Live Chat",
                subtitle: "Live-Anfragen erscheinen sofort mit Klingelton. Übernehmen oder ablehnen — in Sekunden.",
                systemImage: "bell.and.waves.left.and.right.fill"
            ),
            OnboardingPage(
                title: "Einstellungen & Sicherheit",
                subtitle: "Erscheinungsbild, Sprache, Sounds, App-Sperre und Datenschutz individuell anpassen.",
                systemImage: "lock.shield.fill"
            )
        ]
    }

    private var notificationsGranted: Bool {
        switch permissions.notificationStatus {
        case .authorized, .provisional, .ephemeral:
            return true
        default:
            return false
        }
    }

    private var notificationsDenied: Bool {
        permissions.notificationStatus == .denied
    }

    private var locationGranted: Bool {
        LocationPermissionService.isAuthorized(locationPermission.status)
    }

    private var locationDenied: Bool {
        locationPermission.status == .denied || locationPermission.status == .restricted
    }

    private var biometricAvailable: Bool {
        appLock.canUseBiometrics
    }

    private var securityPasswordValid: Bool {
        let value = securityPassword.trimmingCharacters(in: .whitespacesAndNewlines)
        let confirm = securityPasswordConfirm.trimmingCharacters(in: .whitespacesAndNewlines)
        guard value == confirm else { return false }
        guard (4...8).contains(value.count) else { return false }
        return value.allSatisfy(\.isNumber)
    }

    private var securityReady: Bool {
        guard securityPasswordValid else { return false }
        if biometricAvailable, enableBiometricProtection {
            return biometricVerified
        }
        return true
    }

    private var deviceSecurityLabel: String {
        let device: String
        switch UIDevice.current.userInterfaceIdiom {
        case .phone: device = "iPhone"
        case .pad: device = "iPad"
        default: device = "Gerät"
        }
        if biometricAvailable {
            return "\(device): \(appLock.biometricTypeLabel) / Gerätecode"
        }
        return "\(device): Gerätecode"
    }

    private var canCompletePostLogin: Bool {
        acceptedTerms && notificationsGranted && locationGranted && securityReady && !isCompleting
    }

    private var isLastPage: Bool {
        pageIndex == pages.count - 1
    }

    var body: some View {
        NavigationStack {
            VStack(spacing: 0) {
                TabView(selection: $pageIndex) {
                    ForEach(Array(pages.enumerated()), id: \.offset) { index, page in
                        onboardingPage(page, index: index)
                            .tag(index)
                    }
                }
                .tabViewStyle(.page(indexDisplayMode: .always))

                controlBar
                    .padding(.horizontal, 20)
                    .padding(.bottom, 24)
                    .padding(.top, 8)
                    .background(.bar)
            }
            .paxScreenBackground()
            .navigationTitle(mode == .firstLaunch ? "Willkommen" : "Einführung")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                if mode == .firstLaunch {
                    ToolbarItem(placement: .topBarTrailing) {
                        Button("Überspringen") {
                            completeOnboarding()
                        }
                    }
                }
                if isLastPage && mode == .postLogin {
                    ToolbarItemGroup(placement: .keyboard) {
                        Spacer()
                        Button("Fertig") {
                            focusedPasswordField = nil
                        }
                    }
                }
            }
        }
        .task {
            await refreshPermissionStatuses()
            acceptedTerms = auth.profile?.termsAccepted ?? false
            enableBiometricProtection = biometricAvailable
        }
        .onChange(of: scenePhase) { phase in
            guard phase == .active else { return }
            Task { await refreshPermissionStatuses() }
        }
    }

    private func onboardingPage(_ page: OnboardingPage, index: Int) -> some View {
        ScrollViewReader { proxy in
            ScrollView {
                VStack(spacing: 24) {
                    Spacer(minLength: isLastPage && mode == .postLogin ? 8 : 32)

                    Image(systemName: page.systemImage)
                        .font(.system(size: 52))
                        .symbolRenderingMode(.hierarchical)
                        .foregroundStyle(.tint)
                        .padding(.bottom, 4)

                    VStack(spacing: 10) {
                        Text(page.title)
                            .font(.title2.weight(.semibold))
                            .multilineTextAlignment(.center)

                        Text(page.subtitle)
                            .font(.body)
                            .multilineTextAlignment(.center)
                            .foregroundStyle(.secondary)
                            .lineSpacing(3)
                            .padding(.horizontal, 4)
                    }

                    if mode == .postLogin && index == pages.count - 1 {
                        complianceSection
                            .padding(.top, 4)
                    }

                    Spacer(minLength: 32)
                }
                .padding(.horizontal, 20)
                .frame(maxWidth: .infinity)
            }
            .scrollDismissesKeyboard(.interactively)
            .onChange(of: focusedPasswordField) { field in
                guard let field else { return }
                withAnimation(.easeInOut(duration: 0.25)) {
                    proxy.scrollTo(field, anchor: .center)
                }
            }
        }
    }

    private var complianceSection: some View {
        VStack(alignment: .leading, spacing: 16) {
            termsSection
            permissionsSection
            securitySection

            if let completionError, !completionError.isEmpty {
                Text(completionError)
                    .font(.caption)
                    .foregroundStyle(PAXTheme.danger)
                    .fixedSize(horizontal: false, vertical: true)
            }
        }
    }

    private var termsSection: some View {
        Toggle("Ich akzeptiere die Nutzungsbedingungen und Datenschutzbedingungen.", isOn: $acceptedTerms)
            .toggleStyle(.switch)
            .font(.subheadline)
            .padding(16)
            .paxCard(.list)
    }

    private var permissionsSection: some View {
        VStack(alignment: .leading, spacing: 14) {
            Text("Berechtigungen")
                .font(.subheadline.weight(.semibold))
                .foregroundStyle(PAXTheme.textPrimary)

            permissionRow(
                title: "Push-Benachrichtigungen",
                detail: notificationsGranted
                    ? "Aktiv"
                    : (notificationsDenied ? "In den Einstellungen aktivieren" : "Für Live-Anfragen und Nachrichten"),
                granted: notificationsGranted,
                denied: notificationsDenied,
                isLoading: notificationsRequestInFlight,
                actionTitle: notificationsDenied ? "Einstellungen" : "Erlauben",
                action: requestNotifications
            )

            permissionRow(
                title: "Standortzugriff",
                detail: locationGranted
                    ? "Aktiv"
                    : (locationDenied ? "In den Einstellungen aktivieren" : "Für standortbezogene Funktionen"),
                granted: locationGranted,
                denied: locationDenied,
                isLoading: locationRequestInFlight,
                actionTitle: locationDenied ? "Einstellungen" : "Erlauben",
                action: requestLocation
            )
        }
        .padding(16)
        .paxCard(.standard)
    }

    private func permissionRow(
        title: String,
        detail: String,
        granted: Bool,
        denied: Bool,
        isLoading: Bool,
        actionTitle: String,
        action: @escaping () -> Void
    ) -> some View {
        HStack(alignment: .center, spacing: 12) {
            VStack(alignment: .leading, spacing: 4) {
                Text(title)
                    .font(.subheadline.weight(.semibold))
                Text(detail)
                    .font(.caption)
                    .foregroundStyle(granted ? PAXTheme.success : (denied ? PAXTheme.danger : PAXTheme.textSecondary))
                    .fixedSize(horizontal: false, vertical: true)
            }
            Spacer(minLength: 8)
            if granted {
                Image(systemName: "checkmark.circle.fill")
                    .font(.title3)
                    .foregroundStyle(PAXTheme.success)
            } else {
                Button {
                    PAXHaptics.light()
                    action()
                } label: {
                    if isLoading {
                        ProgressView()
                            .controlSize(.small)
                            .frame(minWidth: 72)
                    } else {
                        Text(actionTitle)
                    }
                }
                .buttonStyle(.borderedProminent)
                .controlSize(.small)
                .disabled(isLoading)
            }
        }
    }

    private var securitySection: some View {
        VStack(alignment: .leading, spacing: 14) {
            Text("Sicherheits-Setup")
                .font(.subheadline.weight(.semibold))
            Text(deviceSecurityLabel)
                .font(.caption)
                .foregroundStyle(PAXTheme.textSecondary)

            SecureField("Sicherheitscode (4-8 Ziffern)", text: $securityPassword)
                .keyboardType(.numberPad)
                .textContentType(.oneTimeCode)
                .textInputAutocapitalization(.never)
                .focused($focusedPasswordField, equals: .password)
                .padding(.horizontal, 14)
                .padding(.vertical, 12)
                .background(
                    RoundedRectangle(cornerRadius: 12, style: .continuous)
                        .fill(PAXTheme.surface.opacity(0.55))
                )
                .overlay(
                    RoundedRectangle(cornerRadius: 12, style: .continuous)
                        .stroke(PAXTheme.border.opacity(0.35), lineWidth: 0.5)
                )
                .id(OnboardingPasswordField.password)

            SecureField("Sicherheitscode bestätigen", text: $securityPasswordConfirm)
                .keyboardType(.numberPad)
                .textContentType(.oneTimeCode)
                .textInputAutocapitalization(.never)
                .focused($focusedPasswordField, equals: .confirm)
                .padding(.horizontal, 14)
                .padding(.vertical, 12)
                .background(
                    RoundedRectangle(cornerRadius: 12, style: .continuous)
                        .fill(PAXTheme.surface.opacity(0.55))
                )
                .overlay(
                    RoundedRectangle(cornerRadius: 12, style: .continuous)
                        .stroke(PAXTheme.border.opacity(0.35), lineWidth: 0.5)
                )
                .id(OnboardingPasswordField.confirm)

            if !securityPassword.isEmpty || !securityPasswordConfirm.isEmpty {
                Text(securityPasswordValid ? "Sicherheitscode gültig" : "Bitte 4-8 identische Ziffern eingeben")
                    .font(.caption)
                    .foregroundStyle(securityPasswordValid ? PAXTheme.success : PAXTheme.textSecondary)
            }

            if biometricAvailable {
                Toggle("Biometrische Anmeldung aktivieren (\(appLock.biometricTypeLabel))", isOn: $enableBiometricProtection)
                    .toggleStyle(.switch)
                    .font(.subheadline)

                if enableBiometricProtection {
                    Button(biometricVerified ? "Biometrie bestätigt" : "Biometrie jetzt aktivieren") {
                        Task { await verifyBiometricSetup() }
                    }
                    .buttonStyle(.borderedProminent)
                    .controlSize(.small)
                    .disabled(biometricRequestInFlight)
                }
            }
        }
        .padding(16)
        .paxCard(.standard)
    }

    private var controlBar: some View {
        HStack(spacing: 12) {
            if pageIndex > 0 {
                Button("Zurück") {
                    focusedPasswordField = nil
                    PAXHaptics.light()
                    withAnimation { pageIndex -= 1 }
                }
                .buttonStyle(.bordered)
            }

            Spacer()

            if pageIndex < pages.count - 1 {
                Button("Weiter") {
                    focusedPasswordField = nil
                    PAXHaptics.light()
                    withAnimation { pageIndex += 1 }
                }
                .buttonStyle(.borderedProminent)
            } else {
                Button(mode == .firstLaunch ? "Loslegen" : "Zugriff aktivieren") {
                    focusedPasswordField = nil
                    PAXHaptics.success()
                    completeOnboarding()
                }
                .buttonStyle(.borderedProminent)
                .fontWeight(.semibold)
                .disabled(mode == .postLogin ? !canCompletePostLogin : false)
            }
        }
    }

    private func refreshPermissionStatuses() async {
        await permissions.refreshStatuses()
        locationPermission.refreshStatus()
    }

    private func requestNotifications() {
        guard !notificationsRequestInFlight else { return }
        Task {
            notificationsRequestInFlight = true
            defer { notificationsRequestInFlight = false }

            if notificationsDenied {
                permissions.openSystemSettings()
                return
            }

            await permissions.refreshStatuses()
            if notificationsGranted {
                await push.registerTokenWithBackend(auth: auth)
                return
            }

            _ = await push.requestAuthorization()
            try? await Task.sleep(nanoseconds: 250_000_000)
            await permissions.refreshStatuses()

            if notificationsGranted {
                completionError = nil
                await push.registerTokenWithBackend(auth: auth)
            } else if permissions.notificationStatus == .denied {
                completionError = "Push-Benachrichtigungen wurden abgelehnt. Bitte in den Einstellungen aktivieren."
            }
        }
    }

    private func requestLocation() {
        guard !locationRequestInFlight else { return }
        Task {
            locationRequestInFlight = true
            defer { locationRequestInFlight = false }

            if locationDenied {
                permissions.openSystemSettings()
                return
            }

            locationPermission.refreshStatus()
            if locationGranted { return }

            _ = await locationPermission.requestWhenInUse()
            try? await Task.sleep(nanoseconds: 250_000_000)
            locationPermission.refreshStatus()
        }
    }

    private func completeOnboarding() {
        switch mode {
        case .firstLaunch:
            settings.firstLaunchOnboardingCompleted = true
            settings.onboardingCompleted = true
            onComplete()
        case .postLogin:
            guard canCompletePostLogin else {
                completionError = "Bitte Bedingungen akzeptieren und alle Berechtigungen aktivieren."
                return
            }
            guard configureSecuritySetup() else {
                return
            }
            completionError = nil
            isCompleting = true
            Task {
                defer { isCompleting = false }
                do {
                    let updatedProfile = try await auth.api?.completeOnboarding(
                        termsAccepted: true,
                        permissionStatus: OnboardingPermissionStatus(
                            notifications: notificationStatusCode(permissions.notificationStatus),
                            location: locationStatusCode(locationPermission.status)
                        ),
                        securityStatus: [
                            "device_type": UIDevice.current.userInterfaceIdiom == .phone ? "iphone" : "ipad",
                            "biometric_available": biometricAvailable,
                            "biometric_enabled": biometricAvailable ? enableBiometricProtection : false,
                            "pin_enabled": true,
                            "password_confirmed": securityPasswordValid
                        ]
                    )
                    if let updatedProfile {
                        auth.applyProfileUpdate(updatedProfile)
                    }
                    settings.onboardingCompleted = true
                    await push.registerTokenWithBackend(auth: auth)
                    onComplete()
                } catch {
                    completionError = error.localizedDescription
                }
            }
        }
    }

    private func verifyBiometricSetup() async {
        biometricRequestInFlight = true
        defer { biometricRequestInFlight = false }
        biometricVerified = await appLock.verifyDeviceOwnerForSetup()
        if biometricVerified {
            completionError = nil
        } else {
            completionError = "Biometrische Aktivierung konnte nicht bestätigt werden."
        }
    }

    private func configureSecuritySetup() -> Bool {
        completionError = nil
        guard securityPasswordValid else {
            completionError = "Bitte einen gültigen Sicherheitscode (4-8 Ziffern) vergeben und bestätigen."
            return false
        }
        if biometricAvailable, enableBiometricProtection, !biometricVerified {
            completionError = "Bitte Biometrie bestätigen, bevor Sie fortfahren."
            return false
        }
        do {
            try appLock.setPIN(securityPassword)
            appLock.pinEnabled = true
            appLock.lockEnabled = true
            appLock.lockOnLaunch = true
            appLock.autoLockInterval = .oneMinute
            appLock.biometricEnabled = biometricAvailable ? enableBiometricProtection : false
            return true
        } catch {
            completionError = error.localizedDescription
            return false
        }
    }

    private func notificationStatusCode(_ status: UNAuthorizationStatus) -> String {
        switch status {
        case .authorized: return "authorized"
        case .provisional: return "provisional"
        case .ephemeral: return "ephemeral"
        case .denied: return "denied"
        case .notDetermined: return "not_determined"
        @unknown default: return "unknown"
        }
    }

    private func locationStatusCode(_ status: CLAuthorizationStatus) -> String {
        switch status {
        case .authorizedAlways: return "authorized_always"
        case .authorizedWhenInUse: return "authorized_when_in_use"
        case .denied: return "denied"
        case .restricted: return "restricted"
        case .notDetermined: return "not_determined"
        @unknown default: return "unknown"
        }
    }
}
