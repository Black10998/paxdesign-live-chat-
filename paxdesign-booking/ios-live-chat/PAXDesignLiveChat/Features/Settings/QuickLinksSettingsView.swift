import SwiftUI

struct QuickLinksSettingsView: View {
    @EnvironmentObject private var auth: AuthStore

    @State private var links: [QuickLink] = []
    @State private var isLoading = true
    @State private var isSaving = false
    @State private var errorMessage: String?
    @State private var editingLink: EditableQuickLink?
    @State private var showAddSheet = false
    @State private var editMode: EditMode = .inactive

    var body: some View {
        Group {
            if isLoading && links.isEmpty {
                PAXScreenLoadingStack(status: L10n.LoadingQuickLinks, rowCount: 4)
                    .frame(maxWidth: .infinity, maxHeight: .infinity)
            } else {
                List {
                    Section {
                        Text(L10n.SettingsQuickLinksHint)
                            .font(.footnote)
                            .foregroundStyle(PAXTheme.textSecondary)
                    }

                    if links.isEmpty {
                        Section {
                            VStack(spacing: 10) {
                                Image(systemName: "link.badge.plus")
                                    .font(.system(size: 34))
                                    .foregroundStyle(PAXTheme.textTertiary)
                                Text(L10n.ChatQuickLinksEmptyTitle)
                                    .font(.headline)
                                Text(L10n.SettingsQuickLinksEmptyBody)
                                    .font(.subheadline)
                                    .foregroundStyle(PAXTheme.textSecondary)
                                    .multilineTextAlignment(.center)
                            }
                            .frame(maxWidth: .infinity)
                            .padding(.vertical, 12)
                        }
                    } else {
                        Section(L10n.SettingsQuickLinksSection) {
                            ForEach(links) { link in
                                Button {
                                    editingLink = EditableQuickLink(link: link)
                                } label: {
                                    HStack(spacing: 12) {
                                        QuickLinkIconView(icon: link.icon, label: link.label, size: 28)
                                        VStack(alignment: .leading, spacing: 2) {
                                            Text(link.label)
                                                .font(.subheadline.weight(.semibold))
                                                .foregroundStyle(PAXTheme.textPrimary)
                                            Text(link.url)
                                                .font(.caption)
                                                .foregroundStyle(PAXTheme.textSecondary)
                                                .lineLimit(1)
                                        }
                                        Spacer(minLength: 0)
                                        Image(systemName: "line.3.horizontal")
                                            .font(.caption.weight(.semibold))
                                            .foregroundStyle(PAXTheme.textTertiary)
                                    }
                                    .padding(.vertical, 2)
                                }
                                .buttonStyle(.plain)
                            }
                            .onMove(perform: moveLinks)
                            .onDelete(perform: deleteLinks)
                        }
                    }

                    Section {
                        Button {
                            showAddSheet = true
                        } label: {
                            Label(L10n.SettingsQuickLinksAdd, systemImage: "plus.circle.fill")
                        }
                    }

                    if let errorMessage {
                        Section {
                            Text(errorMessage)
                                .font(.footnote)
                                .foregroundStyle(PAXTheme.danger)
                        }
                    }
                }
                .environment(\.editMode, $editMode)
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.SettingsQuickLinksTitle)
        .navigationBarTitleDisplayMode(.inline)
        .toolbar {
            ToolbarItem(placement: .confirmationAction) {
                Button(L10n.CommonSave) {
                    Task { await saveLinks() }
                }
                .disabled(isSaving || isLoading)
            }
            if !links.isEmpty {
                ToolbarItem(placement: .primaryAction) {
                    EditButton()
                }
            }
        }
        .environment(\.editMode, $editMode)
        .task { await loadLinks() }
        .sheet(item: $editingLink) { item in
            QuickLinkEditorSheet(
                link: item,
                isNew: false,
                onSave: { updated in
                    if let index = links.firstIndex(where: { $0.id == updated.id }) {
                        links[index] = updated
                    }
                    editingLink = nil
                },
                onCancel: { editingLink = nil }
            )
        }
        .sheet(isPresented: $showAddSheet) {
            QuickLinkEditorSheet(
                link: EditableQuickLink.new(),
                isNew: true,
                onSave: { created in
                    links.append(created)
                    showAddSheet = false
                },
                onCancel: { showAddSheet = false }
            )
        }
    }

    private func moveLinks(from source: IndexSet, to destination: Int) {
        links.move(fromOffsets: source, toOffset: destination)
    }

    private func deleteLinks(at offsets: IndexSet) {
        links.remove(atOffsets: offsets)
    }

    private func loadLinks() async {
        guard let api = auth.api else {
            isLoading = false
            return
        }
        isLoading = true
        errorMessage = nil
        defer { isLoading = false }
        do {
            let response = try await api.fetchQuickLinks()
            links = response.quickLinks
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    private func saveLinks() async {
        guard let api = auth.api else { return }
        isSaving = true
        errorMessage = nil
        defer { isSaving = false }
        do {
            let saved = try await api.saveQuickLinks(links)
            links = saved.quickLinks
            PAXHaptics.success()
        } catch {
            errorMessage = error.localizedDescription
            PAXHaptics.warning()
        }
    }
}

private struct EditableQuickLink: Identifiable {
    var id: String
    var label: String
    var url: String
    var icon: String

    init(link: QuickLink) {
        id = link.id
        label = link.label
        url = link.url
        icon = link.icon
    }

    static func new() -> EditableQuickLink {
        EditableQuickLink(
            id: UUID().uuidString.lowercased(),
            label: "",
            url: "",
            icon: "svg:link"
        )
    }

    var quickLink: QuickLink {
        QuickLink(id: id, label: label, url: url, icon: icon)
    }
}

private struct QuickLinkEditorSheet: View {
    @State private var draft: EditableQuickLink
    let isNew: Bool
    let onSave: (QuickLink) -> Void
    let onCancel: () -> Void

    private let iconChoices = [
        "svg:link", "svg:services", "svg:projects", "svg:pricing",
        "svg:contact", "svg:about", "svg:faq", "svg:portfolio",
    ]

    init(link: EditableQuickLink, isNew: Bool, onSave: @escaping (QuickLink) -> Void, onCancel: @escaping () -> Void) {
        _draft = State(initialValue: link)
        self.isNew = isNew
        self.onSave = onSave
        self.onCancel = onCancel
    }

    var body: some View {
        NavigationStack {
            Form {
                Section(L10n.SettingsQuickLinksEditorDetails) {
                    TextField(L10n.SettingsQuickLinksFieldLabel, text: $draft.label)
                    TextField(L10n.SettingsQuickLinksFieldURL, text: $draft.url)
                        .textInputAutocapitalization(.never)
                        .keyboardType(.URL)
                }

                Section(L10n.SettingsQuickLinksFieldIcon) {
                    LazyVGrid(columns: [GridItem(.adaptive(minimum: 52), spacing: 10)], spacing: 10) {
                        ForEach(iconChoices, id: \.self) { icon in
                            Button {
                                draft.icon = icon
                            } label: {
                                QuickLinkIconView(icon: icon, label: draft.label, size: 34)
                                    .frame(maxWidth: .infinity)
                                    .padding(.vertical, 8)
                                    .background(
                                        RoundedRectangle(cornerRadius: 10, style: .continuous)
                                            .fill(draft.icon == icon ? PAXTheme.accent.opacity(0.16) : PAXTheme.surface.opacity(0.5))
                                    )
                                    .overlay(
                                        RoundedRectangle(cornerRadius: 10, style: .continuous)
                                            .stroke(draft.icon == icon ? PAXTheme.accent.opacity(0.45) : Color.clear, lineWidth: 1)
                                    )
                            }
                            .buttonStyle(.plain)
                        }
                    }
                    .padding(.vertical, 4)
                }
            }
            .navigationTitle(isNew ? L10n.SettingsQuickLinksAdd : L10n.SettingsQuickLinksEdit)
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button(L10n.CommonCancel) { onCancel() }
                }
                ToolbarItem(placement: .confirmationAction) {
                    Button(L10n.CommonSave) {
                        let label = draft.label.trimmingCharacters(in: .whitespacesAndNewlines)
                        let url = draft.url.trimmingCharacters(in: .whitespacesAndNewlines)
                        guard !label.isEmpty, !url.isEmpty else { return }
                        onSave(QuickLink(id: draft.id, label: label, url: url, icon: draft.icon))
                    }
                    .disabled(
                        draft.label.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
                        || draft.url.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
                    )
                }
            }
        }
    }
}
