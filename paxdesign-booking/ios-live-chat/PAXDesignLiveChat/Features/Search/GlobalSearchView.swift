import SwiftUI

struct GlobalSearchView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @EnvironmentObject private var teamCoordinator: TeamMessagingCoordinator
    @Environment(\.dismiss) private var dismiss
    @State private var query = ""

    private var results: [GlobalSearchResult] {
        GlobalSearchService.search(
            query: query,
            auth: auth,
            coordinator: coordinator,
            teamCoordinator: teamCoordinator
        )
    }

    var body: some View {
        List {
            if query.isEmpty {
                Section(L10n.GlobalSearchHint) {
                    Text(L10n.GlobalSearchPrompt)
                        .foregroundStyle(PAXTheme.textSecondary)
                }
            } else if results.isEmpty {
                Section {
                    Text(L10n.GlobalSearchNoResults)
                        .foregroundStyle(PAXTheme.textSecondary)
                }
            } else {
                Section(L10n.GlobalSearchResults) {
                    ForEach(results) { result in
                        searchResultRow(result)
                    }
                }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.GlobalSearchTitle)
        .navigationBarTitleDisplayMode(.inline)
        .searchable(text: $query, prompt: L10n.SearchPrompt)
        .toolbar {
            ToolbarItem(placement: .cancellationAction) {
                Button(L10n.CommonClose) { dismiss() }
            }
        }
    }

    @ViewBuilder
    private func searchResultRow(_ result: GlobalSearchResult) -> some View {
        switch result.destination {
        case .session(let sessionId):
            NavigationLink(value: sessionId) {
                searchLabel(result)
            }
        case .module(let module):
            NavigationLink {
                platformDestination(for: module)
            } label: {
                searchLabel(result)
            }
        default:
            NavigationLink {
                destinationView(for: result.destination)
            } label: {
                searchLabel(result)
            }
        }
    }

    private func searchLabel(_ result: GlobalSearchResult) -> some View {
        HStack(spacing: 12) {
            Image(systemName: result.module.systemImage)
                .foregroundStyle(result.module.tint)
                .frame(width: 28)
            VStack(alignment: .leading, spacing: 2) {
                Text(result.title).font(.subheadline.weight(.semibold))
                Text(result.subtitle).font(.caption).foregroundStyle(PAXTheme.textSecondary).lineLimit(1)
            }
        }
    }

    @ViewBuilder
    private func destinationView(for destination: GlobalSearchResult.SearchDestination) -> some View {
        switch destination {
        case .task: TasksModuleView()
        case .event: CalendarModuleView()
        case .document: FilesModuleView()
        case .activity: ActivityLogView()
        case .session, .module: EmptyView()
        }
    }
}
