use std::error::Error;
use std::future::Future;
use tracing::error;

pub async fn run_with_error_handling<TError>(
    callback: impl Future<Output = Result<(), TError>> + Send,
) where
    TError: Error,
{
    if let Err(e) = callback.await {
        error!("Task failed: {}", e);
    }
}
