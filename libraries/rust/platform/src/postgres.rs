use crate::async_infra::run_with_error_handling;
use crate::secrets::SecretProvider;
use native_tls::TlsConnector;
use postgres_native_tls::MakeTlsConnector;
use thiserror::Error;
use tokio_postgres::Client;
use tracing::error;

#[derive(Error, Debug)]
pub enum ConnectionError {
    #[error("Failed to read secret")]
    SecretFailed(#[from] crate::secrets::Error),
    #[error("Failed to connect to postgres")]
    PostgresFailed(#[from] tokio_postgres::Error),
    #[error("TLS error")]
    Tls(#[from] native_tls::Error),
}

/// # Errors
/// Will fail if the connection cannot be established, there's an error reading the secert or there's a TLS error.
pub async fn connect(
    secret_provider: &SecretProvider<'_>,
    hostname: &str,
    password_secret: &str,
) -> Result<Client, ConnectionError> {
    let pg_secret = secret_provider.read(password_secret)?;

    // fixme verify the root cert
    let tls_connector = TlsConnector::builder()
        .danger_accept_invalid_certs(true)
        .build()?;

    let (pg_client, pg_connection) = tokio_postgres::connect(
        &format!(
            "host={} sslmode=require user={} password={}",
            hostname,
            pg_secret.username(),
            pg_secret.password()
        ),
        MakeTlsConnector::new(tls_connector),
    )
    .await?;

    tokio::spawn(run_with_error_handling(pg_connection));

    Ok(pg_client)
}
