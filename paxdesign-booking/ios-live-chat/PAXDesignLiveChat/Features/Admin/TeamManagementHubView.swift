import SwiftUI

struct TeamManagementHubView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var teamCoordinator: TeamMessagingCoordinator

    @State private var overview: TeamManagementOverview?
    @State private var members: [StaffMember] = []
    @State private var pendingRequests: [LiveSession] = []
    @State private var policy: TeamContactPolicy?
    @State private var requireAdminApproval = true
    @State private var requireManagerApproval = false
    @State private var isLoading = true
    @State private var errorMessage: String?
    @State private var addEmail = ""
    @State private var isSaving = false
    @State private var editingMember: StaffMember?
    @State private var editEnabled = true
    @State private var editRole: TeamRoleKey = .teamMember
    @State private var editPermissions = AdminPermissions()

    private var isExecutiveDirector: Bool { auth.profile?.isSuperAdmin == true }

    var body: some View {
        Group {
            if isExecutiveDirector {
                managementContent
            } else {
                accessDenied
            }
        }
        .navigationTitle("Team Management")
        .navigationBarTitleDisplayMode(.large)
        .task { await reload() }
        .paxPremiumRefreshable(status: "Team Management wird geladen", rowCount: 5) {
            await reload()
        }
        .sheet(item: $editingMember) { member in
            memberEditSheet(member)
        }
    }

    private var accessDenied: some View {
        VStack(spacing: 16) {
            Image(systemName: "lock.shield")
                .font(.system(size: 48, weight: .light))
                .foregroundStyle(PAXTheme.textSecondary)
            Text("Executive Director only")
                .font(.title3.weight(.semibold))
            Text("Only the Executive Director can access centralized team management.")
                .font(.subheadline)
                .foregroundStyle(PAXTheme.textSecondary)
                .multilineTextAlignment(.center)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .padding()
        .paxScreenBackground()
    }

    private var managementContent: some View {
        List {
            heroSection
            overviewSection
            pendingSection
            hierarchySection
            membersSection
            addMemberSection
            policySection
            messagingSection
            if let errorMessage {
                Section {
                    Text(errorMessage)
                        .font(.footnote)
                        .foregroundStyle(PAXTheme.danger)
                }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
    }

    private var heroSection: some View {
        Section {
            PlatformHeroHeader(
                title: "Team Management",
                subtitle: "Central control for roster, hierarchy, permissions, and conversation requests.",
                systemImage: "person.3.sequence.fill",
                gradient: [.purple, .indigo]
            )
            .listRowInsets(EdgeInsets(top: 8, leading: 0, bottom: 8, trailing: 0))
            .listRowBackground(Color.clear)
            .listRowSeparator(.hidden)
        }
    }

    @ViewBuilder
    private var overviewSection: some View {
        if let overview {
            Section("Overview") {
                LabeledContent("Executive Director", value: overview.executiveDirectorEmail)
                LabeledContent("Team members", value: "\(overview.totalMembers)")
                LabeledContent("Active", value: "\(overview.enabledMembers)")
                LabeledContent("Pending requests", value: "\(overview.pendingRequestCount)")
            }
        } else if isLoading {
            Section {
                PAXScreenLoadingStack(status: "Overview wird geladen", rowCount: 2)
            }
        }
    }

    @ViewBuilder
    private var pendingSection: some View {
        if !pendingRequests.isEmpty {
            Section("Pending requests") {
                ForEach(pendingRequests) { session in
                    VStack(alignment: .leading, spacing: 10) {
                        HStack {
                            SessionAvatarView(name: session.displayName, size: 40, isTeam: true)
                            VStack(alignment: .leading, spacing: 2) {
                                Text(session.displayName)
                                    .font(.body.weight(.semibold))
                                Text(session.otherRoleLabel.isEmpty ? session.requestStatusLabel : session.otherRoleLabel)
                                    .font(.caption)
                                    .foregroundStyle(.purple)
                            }
                            Spacer()
                        }
                        if !session.lastPreview.isEmpty {
                            Text(session.lastPreview)
                                .font(.subheadline)
                                .foregroundStyle(PAXTheme.textSecondary)
                                .lineLimit(2)
                        }
                        HStack(spacing: 12) {
                            Button("Decline") {
                                Task { await respond(session, accept: false) }
                            }
                            .buttonStyle(.bordered)
                            .tint(PAXTheme.danger)
                            Button("Approve") {
                                Task { await respond(session, accept: true) }
                            }
                            .buttonStyle(.borderedProminent)
                            .tint(PAXBrand.accent)
                        }
                    }
                    .padding(.vertical, 4)
                }
            }
        }
    }

    @ViewBuilder
    private var hierarchySection: some View {
        if let overview, !overview.hierarchy.isEmpty {
            Section("Hierarchy") {
                ForEach(overview.hierarchy) { level in
                    if !level.members.isEmpty {
                        DisclosureGroup {
                            ForEach(level.members) { member in
                                HStack {
                                    VStack(alignment: .leading, spacing: 2) {
                                        Text(member.name)
                                            .font(.subheadline.weight(.semibold))
                                        Text(PrivacyMask.email(member.email, revealFull: true))
                                            .font(.caption)
                                            .foregroundStyle(PAXTheme.textSecondary)
                                    }
                                    Spacer()
                                    Text(member.enabled ? "Active" : "Inactive")
                                        .font(.caption2.weight(.semibold))
                                        .foregroundStyle(member.enabled ? PAXTheme.success : PAXTheme.textTertiary)
                                }
                            }
                        } label: {
                            HStack {
                                Text(level.roleLabel)
                                    .font(.subheadline.weight(.semibold))
                                Spacer()
                                Text("\(level.members.count)")
                                    .font(.caption.weight(.bold))
                                    .foregroundStyle(PAXTheme.textSecondary)
                            }
                        }
                    }
                }
            }
        }
    }

    @ViewBuilder
    private var membersSection: some View {
        if isLoading && members.isEmpty {
            Section {
                PAXScreenLoadingStack(status: "Team wird geladen", rowCount: 4)
            }
        } else {
            Section("Team roster") {
                ForEach(members.filter { !$0.isExecutive }) { member in
                    Button {
                        editingMember = member
                        editEnabled = member.enabled
                        editRole = TeamRoleKey(rawValue: member.teamRole ?? "") ?? .teamMember
                        editPermissions = member.permissions
                    } label: {
                        HStack(spacing: 12) {
                            SessionAvatarView(name: member.name, size: 40, isTeam: true)
                            VStack(alignment: .leading, spacing: 4) {
                                Text(member.name)
                                    .font(.body.weight(.semibold))
                                    .foregroundStyle(PAXTheme.textPrimary)
                                Text(member.displayRoleLabel)
                                    .font(.caption.weight(.semibold))
                                    .foregroundStyle(.purple)
                                Text(PrivacyMask.email(member.email, revealFull: true))
                                    .font(.caption)
                                    .foregroundStyle(PAXTheme.textSecondary)
                            }
                            Spacer()
                            Text(member.enabled ? "Active" : "Inactive")
                                .font(.caption2.weight(.semibold))
                                .foregroundStyle(member.enabled ? PAXTheme.success : PAXTheme.textTertiary)
                            Image(systemName: "chevron.right")
                                .font(.caption2.weight(.semibold))
                                .foregroundStyle(PAXTheme.textTertiary)
                        }
                        .padding(.vertical, 2)
                    }
                    .buttonStyle(.plain)
                    .swipeActions(edge: .trailing, allowsFullSwipe: false) {
                        if !member.isProtected {
                            Button(role: .destructive) {
                                PAXDelete.confirm(
                                    message: "This member will be removed from the team roster.",
                                    itemTitle: member.name
                                ) {
                                    Task { await removeMember(member) }
                                }
                            } label: {
                                Label("Remove", systemImage: "trash")
                            }
                        }
                    }
                }
            }
        }
    }

    private var addMemberSection: some View {
        Section("Add team member") {
            HStack {
                TextField("WordPress email", text: $addEmail)
                    .textInputAutocapitalization(.never)
                    .keyboardType(.emailAddress)
                Button("Add") {
                    Task { await addMember() }
                }
                .disabled(addEmail.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty || isSaving)
            }
            Text("New members are added with Team Member role. Promote or demote from the roster.")
                .font(.caption)
                .foregroundStyle(PAXTheme.textSecondary)
        }
    }

    private var policySection: some View {
        Section("Contact policy") {
            Toggle("Require approval for Administrator contact", isOn: $requireAdminApproval)
                .onChange(of: requireAdminApproval) { _ in
                    Task { await savePolicy() }
                }
            Toggle("Require approval for Senior Staff contact", isOn: $requireManagerApproval)
                .onChange(of: requireManagerApproval) { _ in
                    Task { await savePolicy() }
                }
            LabeledContent("Executive Director messaging") {
                Text("Request required")
                    .font(.caption.weight(.semibold))
                    .foregroundStyle(.orange)
            }
            if let policy {
                Text("Everyone except \(policy.edEmail) must submit a conversation request before messaging the Executive Director.")
                    .font(.caption)
                    .foregroundStyle(PAXTheme.textSecondary)
            }
        }
    }

    private var messagingSection: some View {
        Section("Conversations") {
            NavigationLink {
                TeamMessagesHubView()
            } label: {
                Label("Team messages & inbox", systemImage: "bubble.left.and.bubble.right")
            }
        }
    }

    private func memberEditSheet(_ member: StaffMember) -> some View {
        NavigationStack {
            List {
                Section(member.name) {
                    Toggle("Active", isOn: $editEnabled)
                    Picker("Role", selection: $editRole) {
                        ForEach(TeamRoleKey.assignable) { role in
                            Text(role.label).tag(role)
                        }
                    }
                }
                Section("Permissions") {
                    PermissionToggle("View chats", keyPath: \.viewChats, permissions: $editPermissions)
                    PermissionToggle("Reply to chats", keyPath: \.replyChats, permissions: $editPermissions)
                    PermissionToggle("AI assistant", keyPath: \.useAI, permissions: $editPermissions)
                    PermissionToggle("Send images", keyPath: \.sendImages, permissions: $editPermissions)
                    PermissionToggle("Settings", keyPath: \.manageSettings, permissions: $editPermissions)
                    PermissionToggle("Ratings", keyPath: \.viewRatings, permissions: $editPermissions)
                    PermissionToggle("Manage team", keyPath: \.manageUsers, permissions: $editPermissions)
                    PermissionToggle("Security", keyPath: \.accessSecurity, permissions: $editPermissions)
                    PermissionToggle("Team permissions", keyPath: \.manageTeamPermissions, permissions: $editPermissions)
                    PermissionToggle("Customer profiles", keyPath: \.manageCustomerProfiles, permissions: $editPermissions)
                    PermissionToggle("Assign tasks", keyPath: \.assignTeamTasks, permissions: $editPermissions)
                    PermissionToggle("Hub profile", keyPath: \.customizeHubProfile, permissions: $editPermissions)
                }
            }
            .navigationTitle("Edit member")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Cancel") { editingMember = nil }
                }
                ToolbarItem(placement: .confirmationAction) {
                    Button("Save") {
                        Task { await saveMember(member) }
                    }
                    .disabled(isSaving)
                }
            }
        }
        .presentationDetents([.medium, .large])
    }

    private func reload() async {
        guard isExecutiveDirector, let api = auth.api else {
            isLoading = false
            return
        }
        isLoading = true
        defer { isLoading = false }
        do {
            async let overviewTask = api.fetchTeamManagementOverview()
            async let membersTask = api.fetchTeamManagementMembers()
            async let pendingTask = api.fetchTeamManagementPendingRequests()
            overview = try await overviewTask
            members = try await membersTask.members
            pendingRequests = try await pendingTask.sessions
            if let loadedPolicy = overview?.policy {
                policy = loadedPolicy
                requireAdminApproval = loadedPolicy.requireAdminApproval
                requireManagerApproval = loadedPolicy.requireManagerApproval
            }
            errorMessage = nil
            await teamCoordinator.refreshPendingRequests(auth: auth)
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    private func addMember() async {
        guard let api = auth.api else { return }
        isSaving = true
        defer { isSaving = false }
        do {
            _ = try await api.addTeamManagementMember(
                email: addEmail.trimmingCharacters(in: .whitespacesAndNewlines),
                teamRole: TeamRoleKey.teamMember.rawValue
            )
            addEmail = ""
            await reload()
            PAXHaptics.success()
        } catch {
            errorMessage = error.localizedDescription
            PAXHaptics.warning()
        }
    }

    private func saveMember(_ member: StaffMember) async {
        guard let api = auth.api else { return }
        isSaving = true
        defer { isSaving = false }
        do {
            _ = try await api.updateTeamManagementMember(
                userId: member.userId,
                teamRole: editRole.rawValue,
                enabled: editEnabled,
                permissions: editPermissions
            )
            editingMember = nil
            await reload()
            PAXHaptics.success()
        } catch {
            errorMessage = error.localizedDescription
            PAXHaptics.warning()
        }
    }

    private func removeMember(_ member: StaffMember) async {
        guard let api = auth.api else { return }
        do {
            try await api.removeTeamManagementMember(userId: member.userId)
            await reload()
            PAXHaptics.warning()
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    private func savePolicy() async {
        guard let api = auth.api else { return }
        do {
            let response = try await api.saveTeamManagementPolicy(
                requireAdminApproval: requireAdminApproval,
                requireManagerApproval: requireManagerApproval
            )
            policy = response.policy
            PAXHaptics.light()
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    private func respond(_ session: LiveSession, accept: Bool) async {
        _ = await teamCoordinator.respondToRequest(sessionId: session.sessionId, accept: accept, auth: auth)
        await reload()
    }
}

private struct PermissionToggle: View {
    let title: String
    let keyPath: WritableKeyPath<AdminPermissions, Bool>
    @Binding var permissions: AdminPermissions

    init(_ title: String, keyPath: WritableKeyPath<AdminPermissions, Bool>, permissions: Binding<AdminPermissions>) {
        self.title = title
        self.keyPath = keyPath
        self._permissions = permissions
    }

    var body: some View {
        Toggle(title, isOn: Binding(
            get: { permissions[keyPath: keyPath] },
            set: { newValue in
                var copy = permissions
                copy[keyPath: keyPath] = newValue
                permissions = copy
            }
        ))
    }
}
