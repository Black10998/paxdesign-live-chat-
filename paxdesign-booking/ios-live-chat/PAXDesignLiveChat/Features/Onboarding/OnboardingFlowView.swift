import SwiftUI

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
    @State private var pageIndex = 0
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
                subtitle: "Ihre professionelle Plattform für Kundenkommunikation, Teamarbeit und Live-Support — alles in einer App.",
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
                subtitle: "Live-Anfragen erscheinen sofort mit einem deutlichen Klingelton. Übernehmen oder ablehnen — in Sekunden.",
                systemImage: "bell.and.waves.left.and.right.fill"
            ),
            OnboardingPage(
                title: "KI-Funktionen",
                subtitle: "KI-Vorschläge und Schnellantworten beschleunigen Ihre Antworten. Sie behalten die volle Kontrolle.",
                systemImage: "sparkles"
            ),
            OnboardingPage(
                title: "Einstellungen & Sicherheit",
                subtitle: "Erscheinungsbild, Sprache, Sounds, App-Sperre und Datenschutz — individuell anpassbar.",
                systemImage: "lock.shield.fill"
            )
        ]
    }

    var body: some View {
        NavigationStack {
            VStack(spacing: 0) {
                TabView(selection: $pageIndex) {
                    ForEach(Array(pages.enumerated()), id: \.offset) { index, page in
                        onboardingPage(page)
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
    }

    private func onboardingPage(_ page: OnboardingPage) -> some View {
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

            Spacer()
            Spacer(minLength: 32)
        }
        .padding(.horizontal, 24)
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
                Button("Loslegen") {
                    PAXHaptics.success()
                    completeOnboarding()
                }
                .buttonStyle(.borderedProminent)
                .fontWeight(.semibold)
            }
        }
    }

    private func completeOnboarding() {
        switch mode {
        case .firstLaunch:
            settings.firstLaunchOnboardingCompleted = true
            settings.onboardingCompleted = true
        case .postLogin:
            settings.onboardingCompleted = true
            Task {
                try? await auth.api?.completeOnboarding()
            }
        }
        onComplete()
    }
}
