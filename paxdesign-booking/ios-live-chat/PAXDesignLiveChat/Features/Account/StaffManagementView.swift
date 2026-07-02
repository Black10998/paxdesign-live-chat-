import SwiftUI

struct StaffManagementView: View {
    @EnvironmentObject private var auth: AuthStore
    @State private var staff: [StaffMember] = []
    @State private var isLoading = true
    @State private var errorMessage: String?

    var body: some View {
        List {
            Section {
                Text("Verwalten Sie Mitarbeiter-Zugang zur Live-Chat-App. Der Hauptadministrator hat immer volle Rechte. Erweiterte Bearbeitung auch im WordPress-Admin unter Live Chat Team.")
                    .font(.footnote)
                    .foregroundStyle(PAXTheme.textSecondary)
            }

            if isLoading {
                Section { ProgressView().frame(maxWidth: .infinity) }
            } else if let errorMessage {
                Section {
                    Text(errorMessage).foregroundStyle(PAXTheme.danger)
                }
            } else if staff.isEmpty {
                Section {
                    Text("Noch keine Mitarbeiter konfiguriert. Fügen Sie Teammitglieder im WordPress-Admin hinzu.")
                        .foregroundStyle(PAXTheme.textSecondary)
                }
            } else {
                Section("Team") {
                    ForEach(staff) { member in
                        VStack(alignment: .leading, spacing: 6) {
                            HStack {
                                Text(member.name)
                                    .font(.headline)
                                Spacer()
                                Text(member.enabled ? "Aktiv" : "Inaktiv")
                                    .font(.caption2.weight(.semibold))
                                    .foregroundStyle(member.enabled ? PAXTheme.success : PAXTheme.textTertiary)
                            }
                            Text(PrivacyMask.email(member.email, revealFull: auth.profile?.isSuperAdmin == true))
                                .font(.caption)
                                .foregroundStyle(PAXTheme.textSecondary)
                        }
                        .padding(.vertical, 4)
                    }
                }
            }

            Section {
                Link(destination: PAXLegalLinks.support) {
                    Label("WordPress Admin öffnen", systemImage: "safari")
                }
            }
        }
        .navigationTitle("Team")
        .navigationBarTitleDisplayMode(.inline)
        .task { await load() }
        .refreshable { await load() }
    }

    private func load() async {
        guard let api = auth.api else { return }
        isLoading = true
        defer { isLoading = false }
        do {
            let response = try await api.fetchStaff()
            staff = response.staff
            errorMessage = nil
        } catch {
            errorMessage = error.localizedDescription
        }
    }
}
