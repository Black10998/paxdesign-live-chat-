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

private enum PostLoginStep: Int, CaseIterable {
    case terms
    case notifications
    case location
    case securityPIN
    case biometrics
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
    @State private var postLoginStep: PostLoginStep = .terms
    @State private var acceptedTerms = false
    @State private var isProcessingStep = false
    @State private var isCompleting = false
    @State private var completionError: String?
    @State private var securityPassword = ""
    @State private var securityPasswordConfirm = ""
    @State private var enableBiometricProtection = true
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
                title: L10n.OnboardingWelcomeTitle,
                subtitle: L10n.OnboardingWelcomeSubtitle,
                systemImage: "bubble.left.and.bubble.right.fill"
            ),
            OnboardingPage(
                title: L10n.OnboardingChatsTitle,
                subtitle: L10n.OnboardingChatsSubtitle,
                systemImage: "list.bullet.rectangle.portrait.fill"
            ),
            OnboardingPage(
                title: L10n.OnboardingTeamTitle,
                subtitle: L10n.OnboardingTeamSubtitle,
                systemImage: "person.3.fill"
            ),
            OnboardingPage(
                title: L10n.OnboardingLiveChatTitle,
                subtitle: L10n.OnboardingLiveChatSubtitle,
                systemImage: "bell.and.waves.left.and.right.fill"
            ),
            OnboardingPage(
                title: L10n.OnboardingSettingsTitle,
                subtitle: L10n.OnboardingSettingsSubtitle,
                systemImage: "lock.shield.fill"
            )
        ]
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

    private var postLoginSteps: [PostLoginStep] {
        if biometricAvailable {
            return PostLoginStep.allCases
        }
        return PostLoginStep.allCases.filter { $0 != .biometrics }
    }

    private var isLastPostLoginStep: Bool {
        postLoginStep == postLoginSteps.last
    }

    var body: some View {
        Group {
            switch mode {
            case .firstLaunch:
                firstLaunchBody
            case .postLogin:
                postLoginBody
            }
        }
        .task {
            await refreshPermissionStatuses()
            if mode == .postLogin {
                acceptedTerms = auth.profile?.termsAccepted ?? false
                enableBiometricProtection = biometricAvailable
            }
        }
        .onChange(of: scenePhase) { phase in
            guard phase == .active, mode == .postLogin else { return }
            Task { await refreshPermissionStatuses() }
        }
    }

    // MARK: - First launch (intro carousel)

    private var firstLaunchBody: some View {
        NavigationStack {
            VStack(spacing: 0) {
                TabView(selection: $pageIndex) {
                    ForEach(Array(pages.enumerated()), id: \.offset) { index, page in
                        firstLaunchPage(page)
                            .tag(index)
                    }
                }
                .tabViewStyle(.page(indexDisplayMode: .always))

                firstLaunchControlBar
                    .padding(.horizontal, 20)
                    .padding(.bottom, 24)
                    .padding(.top, 8)
                    .background(.bar)
            }
            .paxScreenBackground()
            .navigationTitle(L10n.OnboardingNavWelcome)
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .topBarTrailing) {
                    Button(L10n.CommonSkip) {
                        completeFirstLaunch()
                    }
                }
            }
        }
    }

    private func firstLaunchPage(_ page: OnboardingPage) -> some View {
        VStack(spacing: 24) {
            Spacer()

            PAXIcon(page.systemImage, size: .hero)
                .padding(.bottom, 8)

            VStack(spacing: 10) {
                Text(page.title)
                    .font(.title2.weight(.semibold))
                    .multilineTextAlignment(.center)

                Text(page.subtitle)
                    .font(.body)
                    .multilineTextAlignment(.center)
                    .foregroundStyle(.secondary)
                    .lineSpacing(3)
                    .padding(.horizontal, 12)
            }

            Spacer()
            Spacer(minLength: 24)
        }
        .padding(.horizontal, 24)
    }

    private var firstLaunchControlBar: some View {
        HStack(spacing: 12) {
            if pageIndex > 0 {
                Button(L10n.CommonBack) {
                    PAXHaptics.light()
                    withAnimation { pageIndex -= 1 }
                }
                .buttonStyle(.bordered)
            }

            Spacer()

            if pageIndex < pages.count - 1 {
                Button(L10n.CommonNext) {
                    PAXHaptics.light()
                    withAnimation { pageIndex += 1 }
                }
                .buttonStyle(.borderedProminent)
            } else {
                Button(L10n.OnboardingGetStarted) {
                    PAXHaptics.success()
                    completeFirstLaunch()
                }
                .buttonStyle(.borderedProminent)
                .fontWeight(.semibold)
            }
        }
    }

    // MARK: - Post-login (native system permission flow)

    private var postLoginBody: some View {
        NavigationStack {
            VStack(spacing: 0) {
                ScrollView {
                    VStack(spacing: 28) {
                        postLoginStepHeader

                        postLoginStepContent
                            .padding(.horizontal, 20)

                        if let completionError, !completionError.isEmpty {
                            Text(completionError)
                                .font(.caption)
                                .foregroundStyle(PAXTheme.danger)
                                .multilineTextAlignment(.center)
                                .padding(.horizontal, 20)
                        }

                        Spacer(minLength: 24)
                    }
                    .padding(.top, 24)
                    .padding(.bottom, 16)
                }
                .scrollDismissesKeyboard(.interactively)

                postLoginControlBar
                    .padding(.horizontal, 20)
                    .padding(.bottom, 24)
                    .padding(.top, 12)
                    .background(.bar)
            }
            .paxScreenBackground()
            .navigationTitle(L10n.OnboardingNavSetup)
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItemGroup(placement: .keyboard) {
                    Spacer()
                    Button(L10n.CommonDone) {
                        focusedPasswordField = nil
                    }
                }
            }
        }
    }

    private var postLoginStepHeader: some View {
        VStack(spacing: 20) {
            PAXIcon(postLoginStepIcon, size: .hero)

            VStack(spacing: 10) {
                Text(postLoginStepTitle)
                    .font(.title2.weight(.semibold))
                    .multilineTextAlignment(.center)

                Text(postLoginStepSubtitle)
                    .font(.body)
                    .multilineTextAlignment(.center)
                    .foregroundStyle(.secondary)
                    .lineSpacing(3)
                    .padding(.horizontal, 24)
            }
        }
        .padding(.horizontal, 20)
    }

    @ViewBuilder
    private var postLoginStepContent: some View {
        switch postLoginStep {
        case .terms:
            termsStepContent
        case .notifications, .location:
            nativePermissionHint
        case .securityPIN:
            securityPINStepContent
        case .biometrics:
            biometricsStepContent
        }
    }

    private var termsStepContent: some View {
        Toggle(L10n.OnboardingTermsToggle, isOn: $acceptedTerms)
            .toggleStyle(.switch)
            .font(.subheadline)
            .padding(16)
            .paxCard(.list)
    }

    private var nativePermissionHint: some View {
        Text(L10n.OnboardingPermissionHint)
            .font(.footnote)
            .foregroundStyle(PAXTheme.textSecondary)
            .multilineTextAlignment(.center)
            .padding(.horizontal, 8)
    }

    private var securityPINStepContent: some View {
        VStack(alignment: .leading, spacing: 14) {
            SecureField(L10n.OnboardingPinPlaceholder, text: $securityPassword)
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

            SecureField(L10n.OnboardingPinConfirmPlaceholder, text: $securityPasswordConfirm)
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

            if !securityPassword.isEmpty || !securityPasswordConfirm.isEmpty {
                Text(securityPasswordValid ? L10n.OnboardingPinValid : L10n.OnboardingPinInvalid)
                    .font(.caption)
                    .foregroundStyle(securityPasswordValid ? PAXTheme.success : PAXTheme.textSecondary)
            }
        }
        .padding(16)
        .paxCard(.standard)
    }

    private var biometricsStepContent: some View {
        VStack(spacing: 12) {
            Text(L10n.OnboardingBiometricHint(appLock.biometricTypeLabel))
                .font(.footnote)
                .foregroundStyle(PAXTheme.textSecondary)
                .multilineTextAlignment(.center)
        }
        .padding(.horizontal, 8)
    }

    private var postLoginControlBar: some View {
        VStack(spacing: 10) {
            if isCompleting {
                ProgressView(L10n.OnboardingCompleting)
                    .font(.subheadline)
                    .frame(maxWidth: .infinity)
            } else {
                Button {
                    focusedPasswordField = nil
                    Task { await handlePostLoginPrimaryAction() }
                } label: {
                    HStack(spacing: 8) {
                        if isProcessingStep {
                            ProgressView()
                                .controlSize(.small)
                        }
                        Text(postLoginPrimaryButtonTitle)
                            .fontWeight(.semibold)
                    }
                    .frame(maxWidth: .infinity)
                }
                .buttonStyle(.borderedProminent)
                .controlSize(.large)
                .disabled(!canProceedFromCurrentStep || isProcessingStep)

                if postLoginShowsSkip {
                    Button(postLoginSkipTitle) {
                        focusedPasswordField = nil
                        Task { await handlePostLoginSkipAction() }
                    }
                    .font(.subheadline.weight(.medium))
                    .foregroundStyle(PAXTheme.textSecondary)
                    .disabled(isProcessingStep || isCompleting)
                }
            }
        }
    }

    private var postLoginStepIcon: String {
        switch postLoginStep {
        case .terms: return "doc.text.fill"
        case .notifications: return "bell.badge.fill"
        case .location: return "location.fill"
        case .securityPIN: return "lock.fill"
        case .biometrics: return biometricStepIcon
        }
    }

    private var biometricStepIcon: String {
        switch appLock.biometricTypeLabel {
        case "Face ID": return "faceid"
        case "Touch ID": return "touchid"
        default: return "lock.shield.fill"
        }
    }

    private var postLoginStepTitle: String {
        switch postLoginStep {
        case .terms: return L10n.OnboardingStepTermsTitle
        case .notifications: return L10n.OnboardingStepNotificationsTitle
        case .location: return L10n.OnboardingStepLocationTitle
        case .securityPIN: return L10n.OnboardingStepPinTitle
        case .biometrics: return appLock.biometricTypeLabel
        }
    }

    private var postLoginStepSubtitle: String {
        switch postLoginStep {
        case .terms: return L10n.OnboardingStepTermsSubtitle
        case .notifications: return L10n.OnboardingStepNotificationsSubtitle
        case .location: return L10n.OnboardingStepLocationSubtitle
        case .securityPIN: return L10n.OnboardingStepPinSubtitle
        case .biometrics: return L10n.OnboardingStepBiometricSubtitle(appLock.biometricTypeLabel)
        }
    }

    private var postLoginPrimaryButtonTitle: String {
        switch postLoginStep {
        case .terms, .notifications, .location: return L10n.CommonNext
        case .securityPIN: return isLastPostLoginStep ? L10n.OnboardingStartApp : L10n.CommonNext
        case .biometrics: return L10n.OnboardingActivateBiometric(appLock.biometricTypeLabel)
        }
    }

    private var postLoginSkipTitle: String {
        switch postLoginStep {
        case .biometrics: return L10n.OnboardingSkipBiometric
        default: return L10n.CommonSkip
        }
    }

    private var postLoginShowsSkip: Bool {
        switch postLoginStep {
        case .notifications, .location, .biometrics:
            return true
        case .terms, .securityPIN:
            return false
        }
    }

    private var canProceedFromCurrentStep: Bool {
        switch postLoginStep {
        case .terms:
            return acceptedTerms
        case .securityPIN:
            return securityPasswordValid
        case .notifications, .location, .biometrics:
            return true
        }
    }

    // MARK: - Post-login step actions

    private func handlePostLoginPrimaryAction() async {
        guard !isProcessingStep, !isCompleting else { return }
        isProcessingStep = true
        completionError = nil
        defer { isProcessingStep = false }

        switch postLoginStep {
        case .terms:
            advancePostLoginStep()
        case .notifications:
            await presentNativeNotificationPermission()
            advancePostLoginStep()
        case .location:
            await presentNativeLocationPermission()
            advancePostLoginStep()
        case .securityPIN:
            guard configureSecurityPIN() else { return }
            if isLastPostLoginStep {
                await finishPostLoginOnboarding()
            } else {
                advancePostLoginStep()
            }
        case .biometrics:
            let verified = await appLock.verifyDeviceOwnerForSetup()
            enableBiometricProtection = verified
            appLock.biometricEnabled = verified
            await finishPostLoginOnboarding()
        }
    }

    private func handlePostLoginSkipAction() async {
        guard !isProcessingStep, !isCompleting else { return }
        isProcessingStep = true
        defer { isProcessingStep = false }

        switch postLoginStep {
        case .notifications, .location:
            advancePostLoginStep()
        case .biometrics:
            enableBiometricProtection = false
            appLock.biometricEnabled = false
            await finishPostLoginOnboarding()
        default:
            break
        }
    }

    private func presentNativeNotificationPermission() async {
        await permissions.refreshStatuses()
        switch permissions.notificationStatus {
        case .notDetermined:
            _ = await push.requestAuthorization()
            try? await Task.sleep(nanoseconds: 300_000_000)
            await permissions.refreshStatuses()
        case .authorized, .provisional, .ephemeral:
            break
        case .denied:
            break
        @unknown default:
            break
        }
        if notificationsGranted {
            await push.registerTokenWithBackend(auth: auth)
        }
    }

    private func presentNativeLocationPermission() async {
        locationPermission.refreshStatus()
        switch locationPermission.status {
        case .notDetermined:
            _ = await locationPermission.requestWhenInUse()
            try? await Task.sleep(nanoseconds: 300_000_000)
            locationPermission.refreshStatus()
        default:
            break
        }
    }

    private var notificationsGranted: Bool {
        switch permissions.notificationStatus {
        case .authorized, .provisional, .ephemeral:
            return true
        default:
            return false
        }
    }

    private func advancePostLoginStep() {
        guard let index = postLoginSteps.firstIndex(of: postLoginStep),
              index + 1 < postLoginSteps.count else {
            Task { await finishPostLoginOnboarding() }
            return
        }
        withAnimation(.easeInOut(duration: 0.25)) {
            postLoginStep = postLoginSteps[index + 1]
        }
    }

    private func configureSecurityPIN() -> Bool {
        guard securityPasswordValid else {
            completionError = L10n.OnboardingErrorPin
            return false
        }
        do {
            try appLock.setPIN(securityPassword)
            appLock.pinEnabled = true
            appLock.lockEnabled = true
            appLock.lockOnLaunch = true
            appLock.autoLockInterval = .oneMinute
            return true
        } catch {
            completionError = error.localizedDescription
            return false
        }
    }

    private func finishPostLoginOnboarding() async {
        guard !isCompleting else { return }
        guard acceptedTerms else {
            completionError = L10n.OnboardingErrorTerms
            postLoginStep = .terms
            return
        }
        guard securityPasswordValid, appLock.hasPINConfigured() else {
            completionError = L10n.OnboardingErrorPinRequired
            postLoginStep = .securityPIN
            return
        }

        isCompleting = true
        completionError = nil
        defer { isCompleting = false }

        appLock.biometricEnabled = biometricAvailable ? enableBiometricProtection : false

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
            PAXHaptics.success()
            onComplete()
        } catch {
            completionError = error.localizedDescription
        }
    }

    // MARK: - Shared

    private func refreshPermissionStatuses() async {
        await permissions.refreshStatuses()
        locationPermission.refreshStatus()
    }

    private func completeFirstLaunch() {
        settings.firstLaunchOnboardingCompleted = true
        settings.onboardingCompleted = true
        onComplete()
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
