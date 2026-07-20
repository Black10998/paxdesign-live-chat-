import SwiftUI

// MARK: - Shimmer infrastructure

private struct SkeletonShimmer: ViewModifier {
    @Environment(\.colorScheme) private var colorScheme
    @State private var phase: CGFloat = -0.6

    func body(content: Content) -> some View {
        content
            .overlay {
                GeometryReader { geo in
                    LinearGradient(
                        colors: [
                            Color.clear,
                            shimmerHighlight.opacity(colorScheme == .dark ? 0.35 : 0.55),
                            Color.clear
                        ],
                        startPoint: .leading,
                        endPoint: .trailing
                    )
                    .frame(width: geo.size.width * 1.8)
                    .offset(x: geo.size.width * phase)
                }
                .clipped()
            }
            .onAppear {
                withAnimation(.linear(duration: 1.35).repeatForever(autoreverses: false)) {
                    phase = 1.1
                }
            }
    }

    private var shimmerHighlight: Color {
        colorScheme == .dark ? Color.white : Color.white
    }
}

extension View {
    func skeletonShimmer() -> some View {
        modifier(SkeletonShimmer())
    }
}

struct SkeletonLine: View {
    @Environment(\.colorScheme) private var colorScheme
    var width: CGFloat? = nil
    var height: CGFloat = 14
    var cornerRadius: CGFloat = 8

    var body: some View {
        RoundedRectangle(cornerRadius: cornerRadius, style: .continuous)
            .fill(baseFill)
            .frame(maxWidth: width == nil ? .infinity : width, minHeight: height, maxHeight: height)
            .skeletonShimmer()
    }

    private var baseFill: Color {
        Color.primary.opacity(colorScheme == .dark ? 0.14 : 0.08)
    }
}

struct SkeletonBlock: View {
    @Environment(\.colorScheme) private var colorScheme
    var height: CGFloat
    var cornerRadius: CGFloat = 12

    var body: some View {
        RoundedRectangle(cornerRadius: cornerRadius, style: .continuous)
            .fill(Color.primary.opacity(colorScheme == .dark ? 0.14 : 0.08))
            .frame(maxWidth: .infinity, minHeight: height, maxHeight: height)
            .skeletonShimmer()
    }
}

struct SkeletonCircle: View {
    @Environment(\.colorScheme) private var colorScheme
    var size: CGFloat

    var body: some View {
        Circle()
            .fill(Color.primary.opacity(colorScheme == .dark ? 0.14 : 0.08))
            .frame(width: size, height: size)
            .skeletonShimmer()
    }
}

struct SkeletonCapsule: View {
    var width: CGFloat = 72
    var height: CGFloat = 28

    var body: some View {
        SkeletonLine(width: width, height: height, cornerRadius: height / 2)
    }
}

// MARK: - Home

struct CustomerHomepageSkeleton: View {
    @Environment(\.marketingTheme) private var theme

    var body: some View {
        ScrollView {
            LazyVStack(spacing: 0) {
                SkeletonBlock(height: min(max(UIScreen.main.bounds.width * 0.88, 340), 440), cornerRadius: 0)
                    .padding(.bottom, 8)

                VStack(spacing: 16) {
                    SkeletonBlock(height: 200, cornerRadius: 18)
                    SkeletonCapsule(width: 120)
                }
                .padding(.horizontal, 20)
                .padding(.vertical, 32)

                VStack(alignment: .leading, spacing: 16) {
                    SkeletonLine(width: 220, height: 22)
                    SkeletonLine(height: 14)
                    ForEach(0..<4, id: \.self) { _ in
                        VStack(alignment: .leading, spacing: 8) {
                            SkeletonLine(width: 160, height: 16)
                            SkeletonLine(height: 12)
                            SkeletonLine(width: 240, height: 12)
                        }
                        .padding(20)
                        .background(theme.cardBackground)
                        .clipShape(RoundedRectangle(cornerRadius: 16, style: .continuous))
                    }
                }
                .padding(.horizontal, 20)
                .padding(.vertical, 32)

                VStack(spacing: 16) {
                    SkeletonLine(width: 180, height: 20)
                    HStack(spacing: 10) {
                        ForEach(0..<4, id: \.self) { _ in SkeletonCapsule(width: 64, height: 30) }
                    }
                    ScrollView(.horizontal, showsIndicators: false) {
                        HStack(spacing: 16) {
                            ForEach(0..<3, id: \.self) { _ in
                                VStack(alignment: .leading, spacing: 0) {
                                    SkeletonBlock(height: 160, cornerRadius: 14)
                                    SkeletonLine(height: 14).padding(12)
                                }
                                .frame(width: 260)
                                .background(theme.cardBackground)
                                .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
                            }
                        }
                        .padding(.horizontal, 20)
                    }
                }
                .padding(.vertical, 32)

                HStack(spacing: 12) {
                    ForEach(0..<3, id: \.self) { _ in
                        VStack(spacing: 8) {
                            SkeletonLine(width: 48, height: 24)
                            SkeletonLine(width: 72, height: 10)
                        }
                        .frame(maxWidth: .infinity)
                        .padding(.vertical, 20)
                        .background(theme.cardBackground)
                        .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
                    }
                }
                .padding(.horizontal, 20)
                .padding(.bottom, 32)
            }
        }
    }
}

// MARK: - Services catalog

struct CustomerServicesCatalogSkeleton: View {
    @Environment(\.colorScheme) private var colorScheme

    private var canvas: Color { colorScheme == .dark ? .black : .white }

    var body: some View {
        ScrollView {
            LazyVStack(alignment: .leading, spacing: 28) {
                VStack(alignment: .leading, spacing: 10) {
                    SkeletonLine(width: 220, height: 18)
                    SkeletonLine(width: 280, height: 14)
                }
                .padding(.horizontal, 22)

                SkeletonBlock(height: 1, cornerRadius: 0)
                    .padding(.horizontal, 22)

                ForEach(0..<4, id: \.self) { _ in
                    VStack(alignment: .leading, spacing: 16) {
                        SkeletonBlock(height: 210, cornerRadius: 22)
                        SkeletonLine(width: 72, height: 10)
                        SkeletonLine(width: 200, height: 22)
                        SkeletonLine(height: 14)
                        SkeletonLine(width: 260, height: 14)
                        HStack(spacing: 14) {
                            SkeletonBlock(height: 48, cornerRadius: 14)
                            SkeletonBlock(height: 48, cornerRadius: 14)
                        }
                    }
                    .padding(.horizontal, 22)
                }
            }
            .padding(.vertical, 24)
        }
        .background(canvas.ignoresSafeArea())
    }
}

// MARK: - Portfolio

struct CustomerPortfolioListSkeleton: View {
    @Environment(\.marketingTheme) private var theme

    var body: some View {
        ScrollView {
            LazyVStack(alignment: .leading, spacing: CustomerCalmDesign.sectionSpacing) {
                HStack {
                    SkeletonCapsule(width: 140, height: 36)
                    Spacer()
                }

                VStack(alignment: .leading, spacing: 12) {
                    HStack(spacing: 8) {
                        ForEach(0..<2, id: \.self) { _ in SkeletonCapsule(width: 72, height: 24) }
                    }
                    SkeletonLine(width: 260, height: 32)
                    SkeletonLine(height: 16)
                    SkeletonLine(width: 300, height: 16)
                }

                ScrollView(.horizontal, showsIndicators: false) {
                    HStack(spacing: 8) {
                        ForEach(0..<4, id: \.self) { _ in SkeletonCapsule(width: 80, height: 34) }
                    }
                }

                ForEach(0..<3, id: \.self) { _ in
                    VStack(alignment: .leading, spacing: 0) {
                        SkeletonBlock(height: 220, cornerRadius: 0)
                        VStack(alignment: .leading, spacing: 10) {
                            SkeletonLine(width: 120, height: 10)
                            SkeletonLine(width: 220, height: 18)
                            SkeletonLine(height: 14)
                            SkeletonLine(width: 280, height: 14)
                            HStack(spacing: 12) {
                                SkeletonBlock(height: 72, cornerRadius: 14)
                                SkeletonBlock(height: 72, cornerRadius: 14)
                            }
                        }
                        .padding(20)
                    }
                    .background(theme.panel)
                    .clipShape(RoundedRectangle(cornerRadius: CustomerCalmDesign.cardRadius, style: .continuous))
                }
            }
            .padding(.horizontal, CustomerCalmDesign.contentPadding)
            .padding(.vertical, 16)
        }
    }
}

struct CustomerPortfolioDetailSkeleton: View {
    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: CustomerPortalDesign.sectionSpacing) {
                SkeletonBlock(height: 240, cornerRadius: CustomerPortalDesign.cardRadius)
                VStack(alignment: .leading, spacing: 12) {
                    SkeletonLine(width: 220, height: 24)
                    SkeletonLine(width: 140, height: 14)
                    HStack(spacing: 8) {
                        ForEach(0..<3, id: \.self) { _ in SkeletonCapsule(width: 64, height: 24) }
                    }
                    SkeletonLine(height: 14)
                    SkeletonLine(height: 14)
                    SkeletonLine(width: 280, height: 14)
                }
                .padding(16)
                .background(PAXTheme.surfaceElevated)
                .clipShape(RoundedRectangle(cornerRadius: CustomerPortalDesign.cardRadius, style: .continuous))

                VStack(alignment: .leading, spacing: 12) {
                    SkeletonLine(width: 80, height: 18)
                    ScrollView(.horizontal, showsIndicators: false) {
                        HStack(spacing: 12) {
                            ForEach(0..<3, id: \.self) { _ in
                                SkeletonBlock(height: 140, cornerRadius: 12).frame(width: 200)
                            }
                        }
                    }
                }
                .padding(16)
                .background(PAXTheme.surfaceElevated)
                .clipShape(RoundedRectangle(cornerRadius: CustomerPortalDesign.cardRadius, style: .continuous))
            }
            .padding()
        }
    }
}

// MARK: - Chat

struct CustomerChatSkeleton: View {
    var body: some View {
        VStack(spacing: 0) {
            ScrollView {
                LazyVStack(alignment: .leading, spacing: 16) {
                    HStack(alignment: .bottom, spacing: 10) {
                        SkeletonCircle(size: 32)
                        VStack(alignment: .leading, spacing: 6) {
                            SkeletonLine(width: 180, height: 14)
                            SkeletonLine(width: 140, height: 14)
                        }
                        .padding(14)
                        .background(PAXTheme.surfaceElevated)
                        .clipShape(RoundedRectangle(cornerRadius: 18, style: .continuous))
                    }
                    HStack {
                        Spacer()
                        VStack(alignment: .trailing, spacing: 6) {
                            SkeletonLine(width: 160, height: 14)
                            SkeletonLine(width: 100, height: 14)
                        }
                        .padding(14)
                        .background(PAXTheme.accentSoft)
                        .clipShape(RoundedRectangle(cornerRadius: 18, style: .continuous))
                    }
                    HStack(alignment: .bottom, spacing: 10) {
                        SkeletonCircle(size: 32)
                        VStack(alignment: .leading, spacing: 6) {
                            SkeletonLine(width: 200, height: 14)
                            SkeletonLine(width: 120, height: 14)
                            SkeletonLine(width: 160, height: 14)
                        }
                        .padding(14)
                        .background(PAXTheme.surfaceElevated)
                        .clipShape(RoundedRectangle(cornerRadius: 18, style: .continuous))
                    }
                }
                .padding()
            }
            Divider()
            HStack(spacing: 10) {
                SkeletonCircle(size: 32)
                SkeletonBlock(height: 40, cornerRadius: 18)
                SkeletonCircle(size: 32)
            }
            .padding(.horizontal, 12)
            .padding(.vertical, 10)
            .background(.ultraThinMaterial)
        }
    }
}

struct CustomerConversationsSkeleton: View {
    var body: some View {
        List {
            ForEach(0..<8, id: \.self) { _ in
                VStack(alignment: .leading, spacing: 8) {
                    SkeletonLine(width: 220, height: 16)
                    SkeletonLine(width: 140, height: 12)
                }
                .padding(.vertical, 6)
                .listRowBackground(PAXTheme.surfaceElevated)
            }
        }
        .listStyle(.insetGrouped)
    }
}

// MARK: - Portal / dashboard

struct CustomerDashboardSkeleton: View {
    var body: some View {
        ScrollView {
            LazyVStack(alignment: .leading, spacing: CustomerPortalDesign.sectionSpacing) {
                CustomerPortalCard {
                    VStack(alignment: .leading, spacing: 8) {
                        SkeletonLine(width: 180, height: 22)
                        SkeletonLine(height: 14)
                        SkeletonLine(width: 260, height: 14)
                    }
                }
                ForEach(0..<4, id: \.self) { _ in
                    CustomerPortalCard {
                        VStack(alignment: .leading, spacing: 12) {
                            SkeletonLine(width: 120, height: 18)
                            ForEach(0..<2, id: \.self) { _ in
                                HStack {
                                    VStack(alignment: .leading, spacing: 4) {
                                        SkeletonLine(width: 160, height: 14)
                                        SkeletonLine(width: 80, height: 10)
                                    }
                                    Spacer()
                                    SkeletonLine(width: 36, height: 14)
                                }
                                Divider()
                            }
                            SkeletonLine(width: 100, height: 14)
                        }
                    }
                }
            }
            .padding()
        }
    }
}

// MARK: - Generic list & detail

struct CustomerListRowsSkeleton: View {
    var rowCount: Int = 6
    var showsAvatar: Bool = false

    var body: some View {
        List {
            ForEach(0..<rowCount, id: \.self) { _ in
                HStack(spacing: 12) {
                    if showsAvatar { SkeletonCircle(size: 40) }
                    VStack(alignment: .leading, spacing: 8) {
                        SkeletonLine(width: 180, height: 16)
                        SkeletonLine(width: 120, height: 12)
                    }
                }
                .padding(.vertical, 4)
            }
        }
    }
}

struct CustomerDetailScrollSkeleton: View {
    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: CustomerPortalDesign.sectionSpacing) {
                SkeletonBlock(height: 180, cornerRadius: CustomerPortalDesign.cardRadius)
                CustomerPortalCard {
                    VStack(alignment: .leading, spacing: 10) {
                        SkeletonLine(width: 200, height: 22)
                        SkeletonLine(height: 14)
                        SkeletonLine(height: 14)
                        SkeletonLine(width: 240, height: 14)
                    }
                }
                CustomerPortalCard {
                    VStack(alignment: .leading, spacing: 10) {
                        SkeletonLine(width: 120, height: 18)
                        ForEach(0..<3, id: \.self) { _ in
                            SkeletonLine(height: 14)
                            Divider()
                        }
                    }
                }
            }
            .padding()
        }
    }
}

struct CustomerFormSkeleton: View {
    var body: some View {
        VStack(alignment: .leading, spacing: 20) {
            SkeletonLine(width: 100, height: 14)
            SkeletonBlock(height: 44, cornerRadius: 10)
            SkeletonLine(width: 80, height: 14)
            SkeletonBlock(height: 120, cornerRadius: 10)
            SkeletonBlock(height: 48, cornerRadius: 12)
        }
        .padding()
    }
}

// MARK: - About / Contact / Legal / Native page

struct CustomerAboutSkeleton: View {
    @Environment(\.marketingTheme) private var theme

    var body: some View {
        ScrollView {
            LazyVStack(spacing: 32) {
                VStack(spacing: 10) {
                    SkeletonLine(width: 120, height: 22)
                    SkeletonLine(width: 260, height: 14)
                }
                VStack(spacing: 12) {
                    SkeletonLine(width: 80, height: 16)
                    SkeletonLine(width: 200, height: 28)
                    SkeletonLine(width: 140, height: 14)
                    SkeletonLine(height: 14)
                    SkeletonLine(width: 280, height: 14)
                }
                .padding(24)
                .background(theme.panel)
                .clipShape(RoundedRectangle(cornerRadius: 16, style: .continuous))
                ForEach(0..<4, id: \.self) { _ in
                    VStack(alignment: .leading, spacing: 8) {
                        SkeletonLine(width: 160, height: 16)
                        SkeletonLine(height: 12)
                        SkeletonLine(width: 240, height: 12)
                    }
                    .padding(20)
                    .background(theme.cardBackground)
                    .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
                }
            }
            .padding(.horizontal, 20)
            .padding(.vertical)
        }
    }
}

struct CustomerContactSkeleton: View {
    @Environment(\.marketingTheme) private var theme

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: CustomerPortalDesign.sectionSpacing) {
                VStack(alignment: .leading, spacing: 12) {
                    SkeletonLine(width: 260, height: 22)
                    SkeletonLine(height: 14)
                    SkeletonLine(width: 280, height: 14)
                }
                .padding(20)
                .background(theme.panel)
                .clipShape(RoundedRectangle(cornerRadius: 16, style: .continuous))

                ForEach(0..<3, id: \.self) { _ in
                    HStack(spacing: 14) {
                        SkeletonCircle(size: 28)
                        VStack(alignment: .leading, spacing: 4) {
                            SkeletonLine(width: 60, height: 10)
                            SkeletonLine(width: 180, height: 14)
                        }
                        Spacer()
                    }
                    .padding(16)
                    .background(theme.cardBackground)
                    .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
                }

                ForEach(0..<3, id: \.self) { _ in
                    VStack(alignment: .leading, spacing: 8) {
                        SkeletonLine(width: 220, height: 14)
                        SkeletonLine(width: 280, height: 12)
                    }
                    .padding(16)
                    .background(theme.cardBackground)
                    .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
                }
            }
            .padding()
        }
    }
}

struct CustomerLegalPageSkeleton: View {
    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: CustomerPortalDesign.sectionSpacing) {
                CustomerPortalCard {
                    VStack(alignment: .leading, spacing: 10) {
                        SkeletonLine(width: 200, height: 24)
                        SkeletonLine(height: 14)
                        SkeletonLine(width: 260, height: 14)
                    }
                }
                ForEach(0..<4, id: \.self) { _ in
                    CustomerPortalCard {
                        VStack(alignment: .leading, spacing: 8) {
                            SkeletonLine(width: 160, height: 16)
                            SkeletonLine(height: 12)
                            SkeletonLine(height: 12)
                            SkeletonLine(width: 220, height: 12)
                        }
                    }
                }
                SkeletonBlock(height: 48, cornerRadius: 12)
            }
            .padding()
        }
    }
}

struct CustomerNativePageSkeleton: View {
    var body: some View {
        CustomerDetailScrollSkeleton()
    }
}

struct CustomerProfileSkeleton: View {
    var body: some View {
        List {
            Section {
                HStack {
                    Spacer()
                    SkeletonCircle(size: 80)
                    Spacer()
                }
                .listRowBackground(Color.clear)
            }
            Section {
                ForEach(0..<3, id: \.self) { _ in
                    HStack {
                        SkeletonLine(width: 80, height: 14)
                        Spacer()
                        SkeletonLine(width: 140, height: 14)
                    }
                }
            }
            Section {
                SkeletonBlock(height: 44, cornerRadius: 10)
            }
            Section {
                ForEach(0..<4, id: \.self) { _ in
                    SkeletonLine(width: 160, height: 16)
                }
            }
        }
    }
}

struct CustomerNewsListSkeleton: View {
    var body: some View {
        ScrollView {
            LazyVStack(spacing: CustomerPortalDesign.sectionSpacing) {
                ForEach(0..<5, id: \.self) { _ in
                    CustomerPortalCard {
                        HStack(spacing: 14) {
                            SkeletonBlock(height: 72, cornerRadius: 12).frame(width: 72)
                            VStack(alignment: .leading, spacing: 8) {
                                SkeletonLine(width: 180, height: 16)
                                SkeletonLine(height: 12)
                                SkeletonLine(width: 80, height: 10)
                            }
                        }
                    }
                }
            }
            .padding()
        }
    }
}

struct CustomerFilesSkeleton: View {
    var body: some View {
        List {
            ForEach(0..<8, id: \.self) { _ in
                HStack(spacing: 12) {
                    SkeletonBlock(height: 36, cornerRadius: 8).frame(width: 36)
                    VStack(alignment: .leading, spacing: 4) {
                        SkeletonLine(width: 160, height: 14)
                        SkeletonLine(width: 80, height: 10)
                    }
                }
            }
        }
    }
}

struct CustomerNotificationsSkeleton: View {
    var body: some View {
        List {
            ForEach(0..<8, id: \.self) { _ in
                VStack(alignment: .leading, spacing: 8) {
                    HStack {
                        SkeletonCapsule(width: 64, height: 22)
                        Spacer()
                        SkeletonLine(width: 48, height: 10)
                    }
                    SkeletonLine(width: 220, height: 14)
                    SkeletonLine(width: 280, height: 12)
                }
                .padding(.vertical, 4)
            }
        }
    }
}
