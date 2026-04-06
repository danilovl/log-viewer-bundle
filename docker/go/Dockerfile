FROM golang:1.24.1-alpine AS builder

ENV CGO_ENABLED=0 GOOS=linux GOARCH=amd64

WORKDIR /app

RUN apk add --no-cache git bash build-base

COPY go.mod ./
RUN go mod download

COPY *.go ./

RUN rm -f go-parser && go build -ldflags="-s -w" -o go-parser .
