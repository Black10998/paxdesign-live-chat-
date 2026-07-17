import SwiftUI

/// Website-aligned palette for the native Services (Leistungen) catalog.
enum ServicesCatalogTheme {
    static let background = Color.black
    static let panel = Color(red: 0.02, green: 0.02, blue: 0.02)
    static let cardBackground = Color(red: 0.04, green: 0.04, blue: 0.04)
    static let border = Color.white.opacity(0.08)
    static let textPrimary = Color(red: 1, green: 1, blue: 1)
    static let textSecondary = Color(red: 0.45, green: 0.45, blue: 0.45)
    static let accent = Color(red: 0.76, green: 1.0, blue: 0.0) // #c2ff00
    static let shadowLight = Color(red: 0.08, green: 0.08, blue: 0.08)
    static let shadowDark = Color.black
    static let linkBlue = Color(red: 0.0, green: 0.4, blue: 0.8)
}

struct ServicesNeumorphicCard<Content: View>: View {
    var highlighted: Bool = false
    @ViewBuilder var content: () -> Content

    var body: some View {
        content()
            .background(ServicesCatalogTheme.cardBackground)
            .clipShape(RoundedRectangle(cornerRadius: 16, style: .continuous))
            .shadow(color: ServicesCatalogTheme.shadowDark, radius: 8, x: 8, y: 8)
            .shadow(color: ServicesCatalogTheme.shadowLight, radius: 8, x: -8, y: -8)
            .overlay {
                if highlighted {
                    RoundedRectangle(cornerRadius: 16, style: .continuous)
                        .stroke(ServicesCatalogTheme.accent.opacity(0.25), lineWidth: 1)
                        .shadow(color: ServicesCatalogTheme.accent.opacity(0.08), radius: 10)
                }
            }
    }
}

struct ServicesRotatingDisc: View {
    @State private var rotation: Double = 0
    var size: CGFloat = 20

    var body: some View {
        Circle()
            .trim(from: 0, to: 0.65)
            .stroke(ServicesCatalogTheme.accent, style: StrokeStyle(lineWidth: 2, dash: [10, 5]))
            .frame(width: size, height: size)
            .rotationEffect(.degrees(rotation))
            .onAppear {
                withAnimation(.linear(duration: 3).repeatForever(autoreverses: false)) {
                    rotation = 360
                }
            }
            .accessibilityHidden(true)
    }
}

struct ServicesLanguageSwitcher: View {
    @Binding var language: CustomerServicesCatalogLanguage

    var body: some View {
        HStack(spacing: 4) {
            ForEach(CustomerServicesCatalogLanguage.allCases) { lang in
                Button {
                    withAnimation(.easeInOut(duration: 0.2)) { language = lang }
                } label: {
                    Text(lang.label)
                        .font(.system(size: 12, weight: .semibold))
                        .tracking(0.5)
                        .frame(minWidth: 44)
                        .padding(.vertical, 8)
                        .padding(.horizontal, 14)
                        .background(language == lang ? ServicesCatalogTheme.cardBackground : Color.clear)
                        .foregroundStyle(language == lang ? ServicesCatalogTheme.textPrimary : ServicesCatalogTheme.textSecondary)
                        .clipShape(Capsule())
                }
                .buttonStyle(.plain)
                .accessibilityAddTraits(language == lang ? .isSelected : [])
            }
        }
        .padding(4)
        .background(ServicesCatalogTheme.panel)
        .overlay(Capsule().stroke(ServicesCatalogTheme.border, lineWidth: 1))
        .clipShape(Capsule())
    }
}

struct ServicesInsetButton: View {
    let title: String
    var action: () -> Void

    var body: some View {
        Button(action: action) {
            Text(title.uppercased())
                .font(.system(size: 15, weight: .bold))
                .tracking(0.5)
                .frame(maxWidth: .infinity)
                .padding(.vertical, 14)
                .foregroundStyle(ServicesCatalogTheme.textPrimary)
                .background(ServicesCatalogTheme.cardBackground)
                .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
                .shadow(color: ServicesCatalogTheme.shadowDark, radius: 4, x: 4, y: 4)
                .shadow(color: ServicesCatalogTheme.shadowLight, radius: 4, x: -4, y: -4)
        }
        .buttonStyle(.plain)
    }
}

struct ServicesCornerRibbon: View {
    let label: String
    var isRTL: Bool = false

    var body: some View {
        ZStack(alignment: isRTL ? .topLeading : .topTrailing) {
            Color.clear
            Text(label)
                .font(.system(size: 10, weight: .heavy))
                .tracking(0.6)
                .textCase(.uppercase)
                .foregroundStyle(Color.black)
                .padding(.vertical, 6)
                .frame(width: 125)
                .background(
                    LinearGradient(
                        colors: [
                            Color(red: 0.83, green: 1, blue: 0.2),
                            ServicesCatalogTheme.accent,
                            Color(red: 0.66, green: 0.9, blue: 0)
                        ],
                        startPoint: .topLeading,
                        endPoint: .bottomTrailing
                    )
                )
                .rotationEffect(.degrees(isRTL ? -45 : 45))
                .offset(x: isRTL ? -32 : 32, y: 16)
        }
        .frame(width: 88, height: 88)
        .clipped()
        .allowsHitTesting(false)
        .accessibilityHidden(true)
    }
}
