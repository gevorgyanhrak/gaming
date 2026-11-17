# The slot engine (game-platform): built from this folder, run on the internal network only (no published port).
FROM golang:1.27-bookworm AS builder
WORKDIR /src
COPY go.mod go.sum ./
RUN go mod download
COPY . .
RUN CGO_ENABLED=0 go build -trimpath -ldflags="-s -w" -o /out/slot-engine .

FROM gcr.io/distroless/static-debian12:nonroot
COPY --from=builder /out/slot-engine /slot-engine
USER nonroot
EXPOSE 8090
ENTRYPOINT ["/slot-engine"]
CMD ["serve"]
