import AVFoundation
import Foundation

enum TeamVoiceWaveformAnalyzer {
    static let minBars = 24
    static let maxBars = 64

    static func barCount(for duration: TimeInterval) -> Int {
        let scaled = Int((max(duration, 0.3) * 4).rounded())
        return min(maxBars, max(minBars, scaled))
    }

    static func levels(from data: Data, duration: TimeInterval) -> [CGFloat] {
        let targetBars = barCount(for: duration)
        guard data.count >= 256 else {
            return Array(repeating: 0.12, count: targetBars)
        }

        let tempURL = FileManager.default.temporaryDirectory
            .appendingPathComponent("pax-wave-\(UUID().uuidString.lowercased()).m4a")
        defer { try? FileManager.default.removeItem(at: tempURL) }

        do {
            try data.write(to: tempURL)
            return try levels(fromFile: tempURL, targetBars: targetBars)
        } catch {
            return Array(repeating: 0.12, count: targetBars)
        }
    }

    static func levels(fromFile url: URL, targetBars: Int) throws -> [CGFloat] {
        let file = try AVAudioFile(forReading: url)
        let format = file.processingFormat
        let frameCount = AVAudioFrameCount(file.length)
        guard frameCount > 0, let buffer = AVAudioPCMBuffer(pcmFormat: format, frameCapacity: frameCount) else {
            return Array(repeating: 0.12, count: targetBars)
        }
        try file.read(into: buffer)
        guard let channel = buffer.floatChannelData?.pointee else {
            return Array(repeating: 0.12, count: targetBars)
        }

        let totalFrames = Int(buffer.frameLength)
        let framesPerBar = max(1, totalFrames / targetBars)
        var levels = [CGFloat]()
        levels.reserveCapacity(targetBars)

        for bar in 0..<targetBars {
            let start = bar * framesPerBar
            let end = min(totalFrames, start + framesPerBar)
            guard end > start else {
                levels.append(0.12)
                continue
            }

            var sum: Float = 0
            for index in start..<end {
                let sample = channel[index]
                sum += sample * sample
            }
            let rms = sqrt(sum / Float(end - start))
            let normalized = max(0.08, min(1, CGFloat(rms) * 5.5))
            levels.append(normalized)
        }

        return levels
    }

    static func encodeForAPI(_ levels: [CGFloat]) -> [Double] {
        levels.map { Double(max(0.05, min(1, $0)).rounded(toPlaces: 3)) }
    }

    static func decodeFromAPI(_ values: [Double]?) -> [CGFloat] {
        guard let values, !values.isEmpty else { return [] }
        return values.map { CGFloat(max(0.05, min(1, $0))) }
    }
}

private extension CGFloat {
    func rounded(toPlaces places: Int) -> CGFloat {
        let factor = pow(10, CGFloat(places))
        return (self * factor).rounded() / factor
    }
}
