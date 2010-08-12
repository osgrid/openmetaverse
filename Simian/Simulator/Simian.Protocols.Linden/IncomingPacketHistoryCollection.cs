﻿/*
 * Copyright (c) Open Metaverse Foundation
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 *    derived from this software without specific prior written permission.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
 * IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
 * OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

using System;
using System.Collections.Generic;

namespace Simian.Protocols.Linden
{
    /// <summary>
    /// A circular buffer and hashset for tracking incoming packet sequence
    /// numbers
    /// </summary>
    public sealed class IncomingPacketHistoryCollection
    {
        private readonly uint[] m_items;
        private HashSet<uint> m_hashSet;
        private int m_first;
        private int m_next;
        private int m_capacity;

        /// <summary>
        /// Constructor
        /// </summary>
        /// <param name="capacity">Fixed capacity of this collection. Once the
        /// collection is full, old entries will be dropped one by one</param>
        public IncomingPacketHistoryCollection(int capacity)
        {
            this.m_capacity = capacity;
            m_items = new uint[capacity];
            m_hashSet = new HashSet<uint>();
        }

        /// <summary>
        /// Attempts to add a seen packet ID to the collection
        /// </summary>
        /// <param name="ack">Packet ID to add</param>
        /// <returns>True if the packet ID did not previously exist in the
        /// collection, otherwise false</returns>
        public bool TryEnqueue(uint ack)
        {
            lock (m_hashSet)
            {
                if (m_hashSet.Add(ack))
                {
                    m_items[m_next] = ack;
                    m_next = (m_next + 1) % m_capacity;
                    if (m_next == m_first)
                    {
                        m_hashSet.Remove(m_items[m_first]);
                        m_first = (m_first + 1) % m_capacity;
                    }

                    return true;
                }
            }

            return false;
        }
    }
}
