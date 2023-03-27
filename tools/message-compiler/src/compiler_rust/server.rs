use proc_macro2::TokenStream;
use quote::quote;

pub fn generate() -> TokenStream {
    quote! {
        pub struct ServerConnection<TRawReader : tokio::io::AsyncBufRead, TRawWriter : tokio::io::AsyncWrite> {
            raw: rpc_support::connection::Connection<TRawReader, TRawWriter>
        }

        // TODO remove the dependency on TCP here
        impl ServerConnection<tokio::io::BufReader<tokio::net::tcp::OwnedReadHalf>, tokio::net::tcp::OwnedWriteHalf> {
            pub fn from_tcp_stream(stream: tokio::net::TcpStream) -> Self {
                let (reader, writer) = stream.into_split();
                let reader = tokio::io::BufReader::new(reader);

                Self {
                    raw: rpc_support::connection::Connection::new(
                        rpc_support::stream::Stream::from_reader_writer(reader, writer)
                    )
                }
            }

            /// # Panics
            /// TODO: make this not panic!
            pub async fn run(self, implementation: std::sync::Arc<impl ResponderRpc>) {
                let request_sender = self.raw.request_sender();
                let dispatcher = std::sync::Arc::new(RpcDispatcher { implementation, other_side: std::sync::Arc::new(ReverseRequester { request_sender }) });

                // TODO no unwraps!
                self.raw.run(dispatcher).await.await.unwrap();
            }
        }
    }
}
