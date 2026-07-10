import SwiftUI

struct FilesModuleView: View {
    @EnvironmentObject private var auth: AuthStore
    @ObservedObject private var store = FileLibraryStore.shared
    @State private var selectedCategory: PAXDocumentItem.DocumentCategory?

    private var visibleDocuments: [PAXDocumentItem] {
        if let selectedCategory {
            return store.documents(in: selectedCategory)
        }
        return store.documents.sorted { $0.modifiedAt > $1.modifiedAt }
    }

    var body: some View {
        List {
            Section {
                ScrollView(.horizontal, showsIndicators: false) {
                    HStack(spacing: 8) {
                        categoryChip(nil, title: L10n.FilterAll)
                        ForEach(PAXDocumentItem.DocumentCategory.allCases) { category in
                            categoryChip(category, title: category.title)
                        }
                    }
                }
                .listRowInsets(EdgeInsets(top: 8, leading: 0, bottom: 8, trailing: 0))
                .listRowBackground(Color.clear)
            }

            Section(L10n.ModuleFiles) {
                if visibleDocuments.isEmpty {
                    Text(L10n.FilesEmpty)
                        .foregroundStyle(PAXTheme.textSecondary)
                } else {
                    ForEach(visibleDocuments) { document in
                        NavigationLink {
                            FileDetailView(document: document)
                        } label: {
                            fileRow(document)
                        }
                        .swipeActions(edge: .trailing, allowsFullSwipe: false) {
                            Button {
                                PAXDelete.confirm(
                                    message: "Diese Datei wird dauerhaft gelöscht.",
                                    itemTitle: document.name
                                ) {
                                    Task { await store.delete(document, auth: auth) }
                                }
                            } label: {
                                Label(L10n.CommonDelete, systemImage: "trash")
                            }
                            .tint(.red)
                        }
                    }
                }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.ModuleFiles)
        .navigationBarTitleDisplayMode(.large)
        .toolbar {
            ToolbarItem(placement: .topBarTrailing) {
                NavigationLink { ModuleFilesSettingsView() } label: {
                    Image(systemName: "slider.horizontal.3")
                }
            }
        }
        .paxPremiumRefreshable(status: L10n.ModuleFiles, rowCount: 4) {
            await PlatformSyncService.shared.sync(auth: auth)
        }
    }

    private func categoryChip(_ category: PAXDocumentItem.DocumentCategory?, title: String) -> some View {
        Button {
            selectedCategory = category
            PAXHaptics.light()
        } label: {
            Text(title)
                .font(.caption.weight(.semibold))
                .padding(.horizontal, 12)
                .padding(.vertical, 7)
                .background(Capsule().fill(selectedCategory == category ? PAXTheme.accentSoft : PAXTheme.surface))
        }
        .buttonStyle(.plain)
    }

    private func fileRow(_ document: PAXDocumentItem) -> some View {
        HStack(spacing: 12) {
            Image(systemName: document.category.systemImage)
                .foregroundStyle(.indigo)
                .frame(width: 28)
            VStack(alignment: .leading, spacing: 3) {
                Text(document.name).font(.subheadline.weight(.semibold))
                Text(document.detail).font(.caption).foregroundStyle(PAXTheme.textSecondary).lineLimit(1)
            }
            Spacer()
            Text(document.sizeLabel)
                .font(.caption2)
                .foregroundStyle(PAXTheme.textTertiary)
        }
    }
}

struct FileDetailView: View {
    let document: PAXDocumentItem

    var body: some View {
        List {
            Section {
                HStack {
                    Image(systemName: document.category.systemImage)
                        .font(.largeTitle)
                        .foregroundStyle(.indigo)
                    VStack(alignment: .leading, spacing: 4) {
                        Text(document.name).font(.headline)
                        Text(document.category.title).font(.caption).foregroundStyle(PAXTheme.textSecondary)
                    }
                }
            }
            Section(L10n.FilesDetails) {
                LabeledContent(L10n.FilesSize, value: document.sizeLabel)
                LabeledContent(L10n.FilesModified, value: document.modifiedAt.formatted(date: .abbreviated, time: .shortened))
            }
            Section {
                Text(document.detail)
                    .font(.subheadline)
                    .foregroundStyle(PAXTheme.textSecondary)
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.FilesDetails)
        .navigationBarTitleDisplayMode(.inline)
    }
}
