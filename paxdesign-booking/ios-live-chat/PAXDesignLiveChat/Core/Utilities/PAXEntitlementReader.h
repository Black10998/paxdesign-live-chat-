#import <Foundation/Foundation.h>

NS_ASSUME_NONNULL_BEGIN

/// Returns the signed entitlement value for `key`, or nil when absent.
NSString * _Nullable PAXCopyEntitlement(NSString *key);

NS_ASSUME_NONNULL_END
