import SwiftUI

struct OnboardingPage: Identifiable {
    let id = UUID()
    let title: String
    let subtitle: String
    let systemImage: String
    let accent: Color
}

struct OnboardingFlowView: View {
    @EnvironmentObject private var auth: AuthStore
    @StateObject private var settings = AppSettingsStore.shared
    @State private var pageIndex = 0
    let onComplete: () -> Void

    private let pages: [OnboardingPage] = [
        OnboardingPage(
            title: "Willkommen bei PAXDesign Live Chat",
            subtitle: "Ihre professionelle Plattform für Kundenkommunikation, Teamarbeit und Live-Support — alles in einer App.",
            systemImage: "bubble.left.and.bubble.right.fill",
            accent: PAXBrand.accent
        ),
        OnboardingPage(
            title: "Chat-Übersicht",
            subtitle: "Alle Kundengespräche auf einen Blick. Filtern, suchen und ungelesene Nachrichten sofort erkennen.",
            systemImage: "list.bullet.rectangle.portrait.fill",
            accent: PAXTheme.accent
        ),
        OnboardingPage(
            title: "Team-Messaging",
            subtitle: "Kommunizieren Sie intern mit Ihrem Team — direkt integriert in die Chat-Liste.",
            systemImage: "person.3.fill",
            accent: .blue
        ),
        OnboardingPage(
            title: "Live Chat",
            subtitle: "Live-Anfragen erscheinen sofort mit einem deutlichen Klingelton. Übernehmen oder ablehnen — in Sekunden.",
            systemImage: "bell.and.waves.left.and.right.fill",
            accent: PAXBrand.accent
        ),
        OnboardingPage(
            title: "KI-Funktionen",
            subtitle: "KI-Vorschläge und Schnellantworten beschleunigen Ihre Antworten. Sie behalten die volle Kontrolle.",
            systemImage: "sparkles",
            accent: .purple
        ),
        OnboardingPage(
            title: "Einstellungen",
            subtitle: "Erscheinungsbild, Sprache, Sounds und Datenschutz — individuell anpassbar für Ihren Workflow.",
            systemImage: "gearshape.fill",
            accent: PAXTheme.textSecondary
        ),
        OnboardingPage(
            title: "Benachrichtigungen",
            subtitle: "Push-Benachrichtigungen und Töne für Kundennachrichten, KI-Hinweise und Live-Anfragen.",
            systemImage: "bell.badge.fill",
            accent: .orange
        ),
        OnboardingPage(
            title: "Sicherheit & Datenschutz",
            subtitle: "App-Sperre, sichere Anmeldung und DSGVO-konforme Kommunikation schützen Ihre Gespräche.",
            systemImage: "lock.shield.fill",
            accent: PAXTheme.success
        )
    ]

    var body: some View {
        ZStack {
            PAXBackground()

            VStack(spacing: 0) {
                TabView(selection: $pageIndex) {
                    ForEach(Array(pages.enumerated()), id: \.offset) { index, page in
                        onboardingPage(page)
                            .tag(index)
                    }
                }
                .tabViewStyle(.page(indexDisplayMode: .always))
                .animation(PAXTheme.spring, value: pageIndex)

                controlBar
                    .padding(.horizontal, 24)
                    .padding(.bottom, 28)
                    .padding(.top, 8)
            }
        }
    }

    private func onboardingPage(_ page: OnboardingPage) -> some View {
        VStack(spacing: 28) {
            Spacer()

            ZStack {
                Circle()
                    .fill(page.accent.opacity(0.14))
                    .frame(width: 120, height: 120)
                Image(systemName: page.systemImage)
                    .font(.system(size: 44, weight: .semibold))
                    .foregroundStyle(page.accent)
            }
            .transition(PAXMotion.cardAppear)

            VStack(spacing: 12) {
                Text(page.title)
                    .font(.title2.weight(.bold))
                    .multilineTextAlignment(.center)
                    .foregroundStyle(PAXTheme.textPrimary)

                Text(page.subtitle)
                    .font(.body)
                    .multilineTextAlignment(.center)
                    .foregroundStyle(PAXTheme.textSecondary)
                    .lineSpacing(4)
                    .padding(.horizontal, 8)
            }

            Spacer()
            Spacer(minLength: 40)
        }
        .padding(.horizontal, 28)
    }

    private var controlBar: some View {
        HStack(spacing: 12) {
            if pageIndex > 0 {
                Button("Zurück") {
                    PAXHaptics.light()
                    withAnimation(PAXTheme.spring) { pageIndex -= 1 }
                }
                .buttonStyle(.bordered)
            }

            Spacer()

            if pageIndex < pages.count - 1 {
                Button("Weiter") {
                    PAXHaptics.light()
                    withAnimation(PAXTheme.spring) { pageIndex += 1 }
                }
                .buttonStyle(.borderedProminent)
                .tint(PAXTheme.accent)
            } else {
                Button("Loslegen") {
                    PAXHaptics.success()
                    Task { await finishOnboarding() }
                }
                .buttonStyle(.borderedProminent)
                .tint(PAXBrand.accent)
                .fontWeight(.semibold)
            }
        }
    }

    private func finishOnboarding() async {
        settings.onboardingCompleted = true
        try? await auth.api?.completeOnboarding()
        onComplete()
    }
}
