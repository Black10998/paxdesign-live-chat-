import SwiftUI
import UIKit

struct ProfileAvatarView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var settings: AppSettingsStore
    var size: CGFloat = 40

    var body: some View {
        Group {
            if let data = settings.profileImageData, let uiImage = UIImage(data: data) {
                Image(uiImage: uiImage)
                    .resizable()
                    .scaledToFill()
            } else if let urlString = auth.profile?.avatarUrl, let url = URL(string: urlString) {
                AsyncImage(url: url) { phase in
                    switch phase {
                    case .success(let image):
                        image.resizable().scaledToFill()
                    default:
                        fallback
                    }
                }
            } else {
                fallback
            }
        }
        .frame(width: size, height: size)
        .clipShape(Circle())
        .overlay(Circle().stroke(PAXTheme.border, lineWidth: 1))
        .accessibilityLabel(auth.profile?.name ?? L10n.CommonAdministrator)
    }

    private var fallback: some View {
        PAXAvatar(name: auth.profile?.name ?? "PAX", size: size)
    }
}
