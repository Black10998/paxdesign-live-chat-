import SwiftUI

struct CustomerCybercrimePortalView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @EnvironmentObject private var auth: CustomerAuthStore
    @EnvironmentObject private var navigation: CustomerNavigationCoordinator
    @State private var list: CustomerCybercrimeListResponse?
    @State private var error: String?
    @State private var isLoading = true
    @State private var showWizard = false
    @State private var showAuth = false

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 24) {
                header
                coverageStrip
                standardsGrid
                processRow
                if auth.isAuthenticated {
                    if let active = list?.active ?? list?.reports.first(where: { $0.isOpen }) {
                        activeCard(active)
                    }
                    startButton
                    historySection
                } else {
                    loginGate
                }
            }
            .padding(.horizontal, PAXSpacing.screenHorizontal)
            .padding(.vertical, 24)
            .padding(.bottom, 32)
        }
        .background(PAXTheme.background.ignoresSafeArea())
        .navigationTitle(String(localized: "Cybercrime Support"))
        .navigationBarTitleDisplayMode(.large)
        .refreshable { await load() }
        .task { await load() }
        .sheet(isPresented: $showWizard) {
            NavigationStack {
                CustomerCybercrimeWizardView {
                    showWizard = false
                    Task { await load() }
                }
                .environmentObject(api)
                .environmentObject(auth)
                .environmentObject(navigation)
            }
        }
        .sheet(isPresented: $showAuth) {
            NavigationStack {
                CustomerLoginView(onRegister: nil, onForgot: nil)
                    .environmentObject(auth)
                    .environmentObject(api)
                    .toolbar {
                        ToolbarItem(placement: .cancellationAction) {
                            Button(String(localized: "Close")) { showAuth = false }
                        }
                    }
            }
        }
        .onChange(of: auth.isAuthenticated) { signedIn in
            if signedIn { Task { await load() } }
        }
    }

    private var header: some View {
        VStack(alignment: .leading, spacing: 12) {
            Text(String(localized: "DIGITAL REPORTING PORTAL").uppercased())
                .font(PAXTypography.labelUpper)
                .tracking(0.8)
                .foregroundStyle(PAXTheme.textTertiary)
            Text(String(localized: "A secure, structured channel for cybercrime reports."))
                .font(PAXTypography.titleLarge)
                .foregroundStyle(PAXTheme.textPrimary)
                .fixedSize(horizontal: false, vertical: true)
            Text(String(localized: "Fraud, account takeovers, phishing, malware, and identity theft are captured in one professional workflow — handled confidentially by the PAXDesign team."))
                .font(PAXTypography.body)
                .foregroundStyle(PAXTheme.textSecondary)
                .fixedSize(horizontal: false, vertical: true)
            HStack(spacing: 8) {
                trustChip(String(localized: "Confidential"))
                trustChip(String(localized: "Secure transfer"))
                trustChip(String(localized: "Structured"))
            }
        }
    }

    private func trustChip(_ title: String) -> some View {
        Text(title)
            .font(PAXTypography.caption.weight(.semibold))
            .foregroundStyle(PAXTheme.textSecondary)
            .padding(.horizontal, 10)
            .padding(.vertical, 6)
            .background(PAXTheme.surfaceElevated, in: Capsule())
            .overlay(Capsule().strokeBorder(PAXTheme.divider, lineWidth: 1))
    }

    private var coverageStrip: some View {
        VStack(alignment: .leading, spacing: 10) {
            Text(String(localized: "Platforms & online services").uppercased())
                .font(PAXTypography.labelUpper)
                .tracking(0.6)
                .foregroundStyle(PAXTheme.textTertiary)
            ScrollView(.horizontal, showsIndicators: false) {
                HStack(spacing: 8) {
                    ForEach(CustomerCybercrimeCatalog.platforms, id: \.self) { name in
                        Text(name)
                            .font(PAXTypography.meta.weight(.semibold))
                            .foregroundStyle(PAXTheme.textPrimary)
                            .padding(.horizontal, 12)
                            .padding(.vertical, 8)
                            .background(PAXTheme.surface, in: Capsule())
                            .overlay(Capsule().strokeBorder(PAXTheme.divider, lineWidth: 1))
                    }
                }
            }
            Text(String(localized: "For illustration only. No partnership or affiliation with listed providers."))
                .font(PAXTypography.caption)
                .foregroundStyle(PAXTheme.textTertiary)
        }
    }

    private var standardsGrid: some View {
        LazyVGrid(columns: [GridItem(.flexible(), spacing: 12), GridItem(.flexible(), spacing: 12)], spacing: 12) {
            standardCard(icon: "lock.shield.fill", title: String(localized: "Confidentiality"), text: String(localized: "Handled in a restricted professional environment."))
            standardCard(icon: "checkmark.shield.fill", title: String(localized: "Security"), text: String(localized: "Encrypted transfer and secure evidence upload."))
            standardCard(icon: "person.text.rectangle", title: String(localized: "Verification"), text: String(localized: "Identity documents may be requested before work continues."))
            standardCard(icon: "list.bullet.rectangle", title: String(localized: "Structured process"), text: String(localized: "Four stages: identity, incident, evidence, review."))
        }
    }

    private func standardCard(icon: String, title: String, text: String) -> some View {
        VStack(alignment: .leading, spacing: 10) {
            PAXRevolutGlyphAvatar(systemImage: icon, size: 36, tint: PAXTheme.accent)
            Text(title)
                .font(PAXTypography.rowTitle)
                .foregroundStyle(PAXTheme.textPrimary)
            Text(text)
                .font(PAXTypography.meta)
                .foregroundStyle(PAXTheme.textSecondary)
                .fixedSize(horizontal: false, vertical: true)
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .paxRevolutSurface(cornerRadius: 16, elevation: 0)
    }

    private var processRow: some View {
        VStack(alignment: .leading, spacing: 12) {
            Text(String(localized: "Reporting process").uppercased())
                .font(PAXTypography.labelUpper)
                .tracking(0.6)
                .foregroundStyle(PAXTheme.textTertiary)
            HStack(spacing: 0) {
                ForEach(Array(["Identity", "Incident", "Evidence", "Review"].enumerated()), id: \.offset) { index, title in
                    VStack(spacing: 6) {
                        Text(String(format: "%02d", index + 1))
                            .font(.system(size: 13, weight: .bold, design: .rounded))
                            .foregroundStyle(PAXTheme.accent)
                        Text(LocalizedStringKey(title))
                            .font(PAXTypography.caption.weight(.semibold))
                            .foregroundStyle(PAXTheme.textSecondary)
                    }
                    .frame(maxWidth: .infinity)
                    if index < 3 {
                        Rectangle()
                            .fill(PAXTheme.divider)
                            .frame(width: 12, height: 1)
                            .offset(y: -8)
                    }
                }
            }
            .padding(16)
            .paxRevolutSurface(cornerRadius: 16, elevation: 0)
            Text(String(localized: "Estimated time: 10–15 minutes"))
                .font(PAXTypography.meta)
                .foregroundStyle(PAXTheme.textTertiary)
        }
    }

    private func activeCard(_ report: CustomerCybercrimeReport) -> some View {
        Button {
            navigation.openCybercrime(reference: report.reference_id)
        } label: {
            VStack(alignment: .leading, spacing: 12) {
                HStack {
                    Text(String(localized: "Your current report").uppercased())
                        .font(PAXTypography.labelUpper)
                        .tracking(0.6)
                        .foregroundStyle(PAXTheme.textTertiary)
                    Spacer()
                    statusPill(report)
                }
                Text(report.reference_id)
                    .font(PAXTypography.subsection)
                    .foregroundStyle(PAXTheme.textPrimary)
                Text(report.displayCategory)
                    .font(PAXTypography.meta)
                    .foregroundStyle(PAXTheme.textSecondary)
                HStack {
                    Text(String(localized: "Open report"))
                        .font(PAXTypography.button)
                    Spacer()
                    PAXIcon("chevron.right", size: .inline, emphasis: .tertiary)
                }
                .foregroundStyle(PAXTheme.accent)
            }
            .padding(18)
            .paxRevolutSurface(cornerRadius: 18, elevation: 1)
        }
        .buttonStyle(PAXRevolutPressableStyle())
    }

    private var startButton: some View {
        PAXRevolutPrimaryButton(title: String(localized: "Start report")) {
            PAXHaptics.light()
            if let active = list?.active ?? list?.reports.first(where: { $0.isOpen }) {
                navigation.openCybercrime(reference: active.reference_id)
            } else {
                showWizard = true
            }
        }
    }

    private var loginGate: some View {
        VStack(alignment: .leading, spacing: 14) {
            Text(String(localized: "Sign in required"))
                .font(PAXTypography.subsection)
                .foregroundStyle(PAXTheme.textPrimary)
            Text(String(localized: "Cybercrime Support is a secure service. Sign in to submit and track reports."))
                .font(PAXTypography.body)
                .foregroundStyle(PAXTheme.textSecondary)
            PAXRevolutPrimaryButton(title: String(localized: "Sign in to continue")) {
                showAuth = true
            }
        }
        .padding(18)
        .paxRevolutSurface(cornerRadius: 18, elevation: 0)
    }

    @ViewBuilder
    private var historySection: some View {
        let history = (list?.history ?? list?.reports.filter { !$0.isOpen }) ?? []
        VStack(alignment: .leading, spacing: 12) {
            Text(String(localized: "Ticket history").uppercased())
                .font(PAXTypography.labelUpper)
                .tracking(0.6)
                .foregroundStyle(PAXTheme.textTertiary)
            if isLoading && list == nil {
                ProgressView()
                    .frame(maxWidth: .infinity)
                    .padding(.vertical, 20)
            } else if let error {
                Text(error)
                    .font(PAXTypography.meta)
                    .foregroundStyle(PAXTheme.danger)
            } else if history.isEmpty {
                Text(String(localized: "No previous reports. Closed reports appear here in read-only mode."))
                    .font(PAXTypography.meta)
                    .foregroundStyle(PAXTheme.textSecondary)
            } else {
                ForEach(history) { report in
                    Button {
                        navigation.openCybercrime(reference: report.reference_id)
                    } label: {
                        PAXRevolutListRow(
                            title: report.reference_id,
                            subtitle: "\(report.displayCategory) · \(report.displayStatus)",
                            leading: {
                                PAXRevolutGlyphAvatar(
                                    systemImage: "shield.checkered",
                                    size: 40,
                                    tint: CustomerCybercrimeCatalog.statusTint(report.status)
                                )
                            },
                            trailing: {
                                PAXIcon("chevron.right", size: .inline, emphasis: .tertiary)
                            }
                        )
                    }
                    .buttonStyle(PAXRevolutPressableStyle())
                    .paxRevolutSurface(cornerRadius: 14, elevation: 0)
                }
            }
        }
    }

    private func statusPill(_ report: CustomerCybercrimeReport) -> some View {
        Text(report.displayStatus)
            .font(PAXTypography.caption.weight(.bold))
            .foregroundStyle(PAXTheme.onAccent)
            .padding(.horizontal, 10)
            .padding(.vertical, 5)
            .background(CustomerCybercrimeCatalog.statusTint(report.status), in: Capsule())
    }

    private func load() async {
        guard auth.isAuthenticated else {
            isLoading = false
            list = nil
            return
        }
        if list == nil { isLoading = true }
        error = nil
        do {
            list = try await api.fetchCybercrimeReports()
        } catch {
            self.error = (error as? CustomerAPIError)?.localizedDescription ?? error.localizedDescription
        }
        isLoading = false
    }
}
