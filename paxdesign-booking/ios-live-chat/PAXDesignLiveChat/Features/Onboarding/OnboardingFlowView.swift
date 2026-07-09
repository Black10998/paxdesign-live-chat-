import SwiftUI
import UserNotifications
import CoreLocation

struct OnboardingPage: Identifiable {
    let id = UUID()
    let title: String
    let subtitle: String
    let systemImage: String
}

struct OnboardingFlowView: View {
    enum Mode {
        case firstLaunch
        case postLogin
    }

    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var settings: AppSettingsStore
    @EnvironmentObject private var push: PushService
    @ObservedObject private var permissions = PermissionCoordinator.shared
    @ObservedObject private var locationPermission = LocationPermissionService.shared

    @State private var pageIndex = 0
    @State private var acceptedTerms = false
    @State private var requestInFlight = false
    @State private var completionError: String?
    @State private var isCompleting = false

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

    private var locationGranted: Bool {
        LocationPermissionService.isAuthorized(locationPermission.status)
    }

    private var canCompletePostLogin: Bool {
        acceptedTerms && notificationsGranted && locationGranted && !isCompleting
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
            }
            .background(Color(.systemGroupedBackground))
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
            }
        }
        .task {
            await permissions.refreshStatuses()
            locationPermission.refreshStatus()
            acceptedTerms = auth.profile?.termsAccepted ?? false
        }
    }

    private func onboardingPage(_ page: OnboardingPage, index: Int) -> some View {
        VStack(spacing: 24) {
            Spacer()

            Image(systemName: page.systemImage)
                .font(.system(size: 52))
                .symbolRenderingMode(.hierarchical)
                .foregroundStyle(.tint)
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

            if mode == .postLogin && index == pages.count - 1 {
                complianceSection
                    .padding(.top, 8)
            }

            Spacer()
            Spacer(minLength: 24)
        }
        .padding(.horizontal, 24)
    }

    private var complianceSection: some View {
        VStack(alignment: .leading, spacing: 12) {
            Toggle("Ich akzeptiere die Nutzungsbedingungen und Datenschutzbedingungen.", isOn: $acceptedTerms)
                .toggleStyle(.switch)

            permissionRow(
                title: "Push-Benachrichtigungen",
                granted: notificationsGranted,
                actionTitle: "Erlauben",
                action: requestNotifications
            )

            permissionRow(
                title: "Standortzugriff",
                granted: locationGranted,
                actionTitle: "Erlauben",
                action: requestLocation
            )

            if let completionError, !completionError.isEmpty {
                Text(completionError)
                    .font(.caption)
                    .foregroundStyle(PAXTheme.danger)
            }
        }
        .padding(14)
        .background(
            RoundedRectangle(cornerRadius: 14, style: .continuous)
                .fill(PAXTheme.surface)
        )
    }

    private func permissionRow(
        title: String,
        granted: Bool,
        actionTitle: String,
        action: @escaping () -> Void
    ) -> some View {
        HStack {
            VStack(alignment: .leading, spacing: 3) {
                Text(title)
                    .font(.subheadline.weight(.semibold))
                Text(granted ? "Aktiv" : "Nicht aktiv")
                    .font(.caption)
                    .foregroundStyle(granted ? PAXTheme.success : PAXTheme.danger)
            }
            Spacer()
            if !granted {
                Button(actionTitle, action: action)
                    .buttonStyle(.borderedProminent)
                    .disabled(requestInFlight)
            } else {
                Image(systemName: "checkmark.circle.fill")
                    .foregroundStyle(PAXTheme.success)
            }
        }
    }

    private var controlBar: some View {
        HStack(spacing: 12) {
            if pageIndex > 0 {
                Button("Zurück") {
                    PAXHaptics.light()
                    withAnimation { pageIndex -= 1 }
                }
                .buttonStyle(.bordered)
            }

            Spacer()

            if pageIndex < pages.count - 1 {
                Button("Weiter") {
                    PAXHaptics.light()
                    withAnimation { pageIndex += 1 }
                }
                .buttonStyle(.borderedProminent)
            } else {
                Button(mode == .firstLaunch ? "Loslegen" : "Zugriff aktivieren") {
                    PAXHaptics.success()
                    completeOnboarding()
                }
                .buttonStyle(.borderedProminent)
                .fontWeight(.semibold)
                .disabled(mode == .postLogin ? !canCompletePostLogin : false)
            }
        }
    }

    private func requestNotifications() {
        Task {
            requestInFlight = true
            defer { requestInFlight = false }
            await push.requestAuthorization()
            await permissions.refreshStatuses()
            if notificationsGranted {
                await push.registerTokenWithBackend(auth: auth)
            }
        }
    }

    private func requestLocation() {
        Task {
            requestInFlight = true
            defer { requestInFlight = false }
            _ = await locationPermission.requestWhenInUse()
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
                        )
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
