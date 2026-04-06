package main

import (
	"os"
	"syscall"
	"unsafe"
)

func Mmap(f *os.File) ([]byte, error) {
	st, err := f.Stat()
	if err != nil {
		return nil, err
	}
	size := st.Size()
	if size == 0 {
		return nil, nil
	}

	data, err := syscall.Mmap(int(f.Fd()), int64(0), int(size), syscall.PROT_READ, syscall.MAP_SHARED)
	if err != nil {
		return nil, err
	}
	return data, nil
}

func Munmap(data []byte) error {
	if len(data) == 0 {
		return nil
	}
	return syscall.Munmap(data)
}

func MadviseSequential(data []byte) {
	if len(data) > 0 {
		syscall.Syscall(syscall.SYS_MADVISE,
			uintptr(unsafe.Pointer(&data[0])),
			uintptr(len(data)),
			2) // MADV_SEQUENTIAL
	}
}

func unsafeString(b []byte) string {
	if len(b) == 0 {
		return ""
	}
	return unsafe.String(unsafe.SliceData(b), len(b))
}
