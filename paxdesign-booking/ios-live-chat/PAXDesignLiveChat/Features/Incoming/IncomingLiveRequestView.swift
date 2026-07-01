import SwiftUI

struct IncomingLiveRequestView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    let request: IncomingLiveRequest

    @State private var pulse = false
    @State private var appear = false

    var body: some View {
        ZStack {
            Color.black.opacity(0.94).ignoresSafeArea()

            VStack(spacing: 28) {
                Spacer()

                ZStack {
                    ForEach(0..<3, id: \.self) { ring in
                        Circle()
                            .stroke(PAXTheme.accent.opacity(0.18 - Double(ring) * 0.04), lineWidth: 2)
                            .frame(width: 120 + CGFloat(ring * 28), height: 120 + CGFloat(ring * 28))
                            .scaleEffect(pulse ? 1.06 : 0.94)
                            .opacity(pulse ? 0.9 : 0.35)
                    }

                    Circle()
                        .fill(PAXTheme.accentSoft)
                        .frame(width: 96, height: 96)

                    Image(systemName: "person.crop.circle.badge.clock.fill")
                        .font(.system(size: 44))
                        .foregroundStyle(PAXTheme.accent)
                }
                .scaleEffect(appear ? 1 : 0.88)
                .opacity(appear ? 1 : 0)
                .onAppear {
                    withAnimation(.easeInOut(duration: 1.2).repeatForever(autoreverses: true)) {
                        pulse = true
                    }
                }

                VStack(spacing: 10) {
                    Text("Live-Agent-Anfrage")
                        .font(.title2.weight(.bold))
                    Text(request.session.displayName)
                        .font(.title3.weight(.semibold))
                    if !request.session.detectedService.isEmpty {
                        Text(request.session.detectedService)
                            .font(.subheadline)
                            .foregroundStyle(PAXTheme.textSecondary)
                    }
                    if !request.session.lastPreview.isEmpty {
                        Text(request.session.lastPreview)
                            .font(.footnote)
                            .foregroundStyle(PAXTheme.textSecondary)
                            .multilineTextAlignment(.center)
                            .padding(.horizontal, 28)
                    }
                }
                .opacity(appear ? 1 : 0)
                .offset(y: appear ? 0 : 12)

                Spacer()

                HStack(spacing: 56) {
                    callAction(
                        title: "Ablehnen",
                        icon: "phone.down.fill",
                        color: PAXTheme.danger
                    ) {
                        PAXHaptics.warning()
                        Task { await coordinator.declineLiveRequest(auth: auth, session: request.session) }
                    }

                    callAction(
                        title: "Annehmen",
                        icon: "phone.fill",
                        color: PAXTheme.success
                    ) {
                        PAXHaptics.success()
                        Task { await coordinator.acceptLiveRequest(auth: auth, session: request.session) }
                    }
                }
                .opacity(appear ? 1 : 0)
                .offset(y: appear ? 0 : 20)

                Spacer().frame(height: 48)
            }
            .padding()
        }
        .onAppear {
            withAnimation(PAXTheme.spring) { appear = true }
            PAXHaptics.medium()
        }
    }

    private func callAction(title: String, icon: String, color: Color, action: @escaping () -> Void) -> some View {
        VStack(spacing: 10) {
            Button(action: action) {
                Image(systemName: icon)
                    .font(.title2.weight(.semibold))
                    .foregroundStyle(.white)
                    .frame(width: 76, height: 76)
                    .background(
                        Circle()
                            .fill(
                                LinearGradient(
                                    colors: [color, color.opacity(0.75)],
                                    startPoint: .topLeading,
                                    endPoint: .bottomTrailing
                                )
                            )
                            .shadow(color: color.opacity(0.35), radius: 16, y: 8)
                    )
            }
            .buttonStyle(PAXPressButtonStyle())

            Text(title)
                .font(.caption.weight(.semibold))
                .foregroundStyle(PAXTheme.textSecondary)
        }
    }
}
