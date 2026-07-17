import SwiftUI

struct ProfileView: View {
    @EnvironmentObject private var auth: AuthStore
    @State private var hubDisplayName = ""
    @State private var isSavingHubName = false
    @State private var hubNameError: String?
    @State private var selectedGender: UserGender?
    @State private var isSavingGender = false
    @State private var genderError: String?

    private var profile: AdminProfile? { auth.profile }
    private var permissions: AdminPermissions { profile?.permissions ?? .full }

    var body: some View {
        List {
            Section {
                HStack(spacing: 18) {
                    ProfileAvatarView(size: 80)
                    VStack(alignment: .leading, spacing: 6) {
                        Text(profile?.displayName ?? L10n.CommonAdministrator)
                            .font(.title2.weight(.bold))
                        roleBadge
                    }
                    .padding(.vertical, 8)
                }
            }

            Section(L10n.ProfileAccountInfo) {
                LabeledContent(L10n.SettingsProfile, value: profile?.displayName ?? L10n.CommonAdministrator)
                    .font(.subheadline)
                if let email = profile?.email, !email.isEmpty {
                    LabeledContent(L10n.LoginUsername, value: email)
                        .font(.subheadline)
                } else if let username = profile?.displayUsernameIfDistinct {
                    LabeledContent(L10n.LoginUsername, value: username)
                        .font(.subheadline)
                }
                Picker(L10n.ProfileGender, selection: $selectedGender) {
                    Text(L10n.ProfileGenderUnset).tag(Optional<UserGender>.none)
                    ForEach(UserGender.allCases) { gender in
                        Text(gender.localizedLabel).tag(Optional(gender))
                    }
                }
                .disabled(isSavingGender)
                if let genderError, !genderError.isEmpty {
                    Text(genderError)
                        .font(.caption)
                        .foregroundStyle(PAXTheme.danger)
                }
                LabeledContent(L10n.CommonPlugin, value: profile?.pluginVer ?? "—")
                    .font(.subheadline)
                LabeledContent(L10n.CommonVersion, value: PAXAppInfo.fullVersion)
                    .font(.subheadline)
            }

            if auth.canCustomizeHubProfile {
                Section {
                    TextField(L10n.ProfileHubDisplayNamePlaceholder, text: $hubDisplayName)
                        .textInputAutocapitalization(.words)
                    if let hubNameError, !hubNameError.isEmpty {
                        Text(hubNameError)
                            .font(.caption)
                            .foregroundStyle(PAXTheme.danger)
                    }
                    Button(L10n.ProfileHubSave) {
                        Task { await saveHubDisplayName() }
                    }
                    .disabled(isSavingHubName || hubDisplayName.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty)
                } header: {
                    Text(L10n.ProfileHubSection)
                } footer: {
                    Text(L10n.ProfileHubFooter)
                }
            }

            Section(L10n.ProfilePermissions) {
        if profile?.isSuperAdmin == true {
            HStack {
                PAXIcon("star.fill", size: .row)
                Text(L10n.RoleExecutiveDirector)
                    .foregroundStyle(PAXTheme.accent)
                        Spacer()
                        Text(L10n.CommonActive)
                            .font(.caption.weight(.semibold))
                            .foregroundStyle(PAXTheme.accent)
                    }
                } else {
                    ForEach(PermissionLabels.items(for: permissions)) { item in
                        PermissionOverviewRow(title: item.title, enabled: item.enabled)
                    }
                }
            }

            Section {
                NavigationLink {
                    SettingsRootView()
                } label: {
                    Label { Text(L10n.AccountSettings) } icon: { PAXIcon("gearshape") }
                }
                NavigationLink {
                    AppLockSettingsView()
                } label: {
                    Label { Text(L10n.SettingsAppLock) } icon: { PAXIcon("lock.shield") }
                }
            }

            Section {
                Button(L10n.SettingsSignOut) {
                    PAXDelete.confirm(
                        title: L10n.SettingsSignOut,
                        message: L10n.SettingsSignOutMessage,
                        confirmTitle: L10n.SettingsSignOut
                    ) {
                        Task {
                            await PushService.shared.unregisterTokenFromBackend(auth: auth)
                            auth.logout()
                        }
                    }
                }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.ProfileTitle)
        .navigationBarTitleDisplayMode(.large)
        .onAppear {
            if hubDisplayName.isEmpty {
                hubDisplayName = profile?.displayName ?? ""
            }
            if selectedGender == nil {
                selectedGender = UserGender(rawValue: profile?.gender ?? "")
            }
        }
        .onChange(of: selectedGender) { _ in
            Task { await saveGender() }
        }
    }

    @ViewBuilder
    private var roleBadge: some View {
        if profile?.isSuperAdmin == true {
            Text(L10n.RoleExecutiveDirector)
                .font(.caption2.weight(.bold))
                .foregroundStyle(PAXTheme.accent)
                .padding(.horizontal, 8)
                .padding(.vertical, 3)
                .background(Capsule().fill(PAXTheme.accentSoft))
        } else if auth.canManageUsers {
            Text(L10n.ProfileRoleManager)
                .font(.caption2.weight(.bold))
                .foregroundStyle(PAXTheme.accent)
                .padding(.horizontal, 8)
                .padding(.vertical, 3)
                .background(Capsule().fill(PAXTheme.accent.opacity(0.14)))
                .overlay(Capsule().stroke(PAXTheme.accent.opacity(0.34), lineWidth: 1))
        } else {
            Text(L10n.ProfileRoleStaff)
                .font(.caption2.weight(.bold))
                .foregroundStyle(PAXTheme.textSecondary)
                .padding(.horizontal, 8)
                .padding(.vertical, 3)
                .background(
                    Capsule()
                        .fill(.ultraThinMaterial)
                        .overlay(Capsule().fill(PAXTheme.surface.opacity(0.75)))
                        .overlay(Capsule().stroke(PAXTheme.border.opacity(0.42), lineWidth: 1))
                )
        }
    }

    private func saveGender() async {
        guard let api = auth.api else { return }
        let current = UserGender(rawValue: profile?.gender ?? "")
        guard selectedGender != current else { return }
        isSavingGender = true
        defer { isSavingGender = false }
        do {
            let updated = try await api.updateGender(selectedGender)
            auth.applyProfileUpdate(updated)
            genderError = nil
            PAXHaptics.light()
        } catch {
            genderError = error.localizedDescription
            selectedGender = current
            PAXHaptics.warning()
        }
    }

    private func saveHubDisplayName() async {
        guard let api = auth.api else { return }
        let clean = hubDisplayName.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !clean.isEmpty else { return }
        isSavingHubName = true
        defer { isSavingHubName = false }
        do {
            let updated = try await api.updateHubDisplayName(clean)
            auth.applyProfileUpdate(updated)
            hubDisplayName = updated.displayName
            hubNameError = nil
            PAXHaptics.success()
        } catch {
            hubNameError = error.localizedDescription
            PAXHaptics.warning()
        }
    }
}
